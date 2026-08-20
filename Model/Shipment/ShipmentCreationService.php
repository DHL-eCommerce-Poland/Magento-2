<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\CreateShipmentsResult;
use Ideo\Dhl\Model\Api\ShipmentFullDataMapper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ShipmentCreationService
{
    private const LOCK_TTL = 15;
    public const STATUS_DISPATCHED = ShipmentStatus::DISPATCHED;
    public const STATUS_PENDING_VERIFICATION = ShipmentStatus::PENDING_VERIFICATION;
    public const STATUS_ERROR_SYNC = ShipmentStatus::ERROR_SYNC;

    /**
     * @param ShipmentDataService $shipmentDataService
     * @param ShipmentDataValidator $validator
     * @param ShipmentFullDataMapper $mapper
     * @param CredentialsProvider $credentialsProvider
     * @param ClientInterface $client
     * @param LockManagerInterface $lockManager
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly ShipmentDataService $shipmentDataService,
        private readonly ShipmentDataValidator $validator,
        private readonly ShipmentFullDataMapper $mapper,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ClientInterface $client,
        private readonly LockManagerInterface $lockManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Validate and dispatch shipment directly to DHL WebAPI2 without saving preliminary draft.
     *
     * Saves to DB only upon API success (status DISPATCHED) or timeout (status PENDING_VERIFICATION).
     *
     * @param DhlShipmentData $shipmentData
     * @param string $environment ('sandbox' or 'production')
     * @param string|null $presetSenderId
     * @param string|null $presetShipmentId
     *
     * @return CreateShipmentsResult
     * @throws LocalizedException
     */
    public function createAndDispatchShipment(
        DhlShipmentData $shipmentData,
        string $environment = 'sandbox',
        ?string $presetSenderId = null,
        ?string $presetShipmentId = null
    ): CreateShipmentsResult {
        $orderId = $shipmentData->getOrderId();
        $hash = hash('sha256', $this->serializer->serialize($shipmentData->toArray()));
        $lockName = 'dhl_shipment_dispatch_' . ($orderId ?? $hash);

        if (!$this->lockManager->lock($lockName, self::LOCK_TTL)) {
            throw new LocalizedException(
                __('Shipment dispatch is already in progress. Please wait a moment.')
            );
        }

        try {
            // 1. Validate payload FIRST before making API call or DB writes
            $this->validator->validate($shipmentData);

            // 2. Prepare DHL API call credentials and data mapping
            $credentials = $this->credentialsProvider->getCredentials($environment);
            $mappedData = $this->mapper->map($shipmentData);

            // 3. Dispatch directly to DHL WebAPI2 (createShipments)
            $apiResult = $this->client->createShipments($credentials, [$mappedData], $orderId);

            // 4. Handle Timeout scenario (save as PENDING_VERIFICATION to prevent duplicate creations)
            if ($apiResult->isTimeout()) {
                $this->shipmentDataService->saveShipmentDataWithStatus(
                    $shipmentData,
                    self::STATUS_PENDING_VERIFICATION,
                    $apiResult->getErrorMessage(),
                    $presetSenderId,
                    $presetShipmentId
                );
                return $apiResult;
            }

            // 5. Handle API Failure scenario (do NOT save anything in DB)
            if (!$apiResult->isSuccess()) {
                return $apiResult;
            }

            // 6. Handle API Success scenario (save DB entity directly as DISPATCHED)
            $this->shipmentDataService->saveShipmentDataWithStatus(
                $shipmentData,
                self::STATUS_DISPATCHED,
                null,
                $presetSenderId,
                $presetShipmentId,
                $apiResult->getShipmentId()
            );

            return $apiResult;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * Dispatch shipment to DHL API with locking, transaction safety, and error handling for existing entity.
     *
     * @param int $entityId
     * @param string $environment ('sandbox' or 'production')
     *
     * @return CreateShipmentsResult
     * @throws LocalizedException
     */
    public function dispatchShipment(int $entityId, string $environment = 'sandbox'): CreateShipmentsResult
    {
        $lockName = 'dhl_shipment_dispatch_' . $entityId;

        if (!$this->lockManager->lock($lockName, self::LOCK_TTL)) {
            throw new LocalizedException(
                __('Shipment dispatch is already in progress. Please wait a moment.')
            );
        }

        try {
            $shipmentData = $this->shipmentDataService->loadShipmentData($entityId);
            $this->validator->validate($shipmentData);

            $credentials = $this->credentialsProvider->getCredentials($environment);
            $mappedData = $this->mapper->map($shipmentData);

            $apiResult = $this->client->createShipments($credentials, [$mappedData], $shipmentData->getOrderId());

            if ($apiResult->isTimeout()) {
                $this->updateStatus($entityId, self::STATUS_PENDING_VERIFICATION, null, $apiResult->getErrorMessage());
                return $apiResult;
            }

            if (!$apiResult->isSuccess()) {
                return $apiResult;
            }

            $shipmentId = (string) $apiResult->getShipmentId();

            $connection = $this->resourceConnection->getConnection();
            $connection->beginTransaction();

            try {
                $this->shipmentDataService->updateShipmentStatusAndTracking(
                    $entityId,
                    self::STATUS_DISPATCHED,
                    $shipmentId
                );

                $connection->commit();
            } catch (Throwable $dbEx) {
                $connection->rollBack();

                $this->logger->critical(
                    sprintf(
                        '[DHL] Critical: Shipment created in DHL (Tracking: %s), '
                        . 'but Magento DB transaction failed for entity #%d: %s',
                        $shipmentId,
                        $entityId,
                        $dbEx->getMessage()
                    ),
                    ['exception' => $dbEx]
                );

                $this->updateStatusFallback(
                    $entityId,
                    self::STATUS_ERROR_SYNC,
                    $shipmentId,
                    $dbEx->getMessage()
                );

                throw new LocalizedException(__(
                    'Shipment was created in DHL (Tracking #: %1), but saving to Magento DB failed: %2',
                    $shipmentId,
                    $dbEx->getMessage()
                ), $dbEx);
            }

            return $apiResult;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * Safely update shipment status.
     *
     * @param int $entityId
     * @param string $status
     * @param string|null $shipmentId
     * @param string|null $errorMsg
     *
     * @return void
     */
    private function updateStatus(
        int $entityId,
        string $status,
        ?string $shipmentId = null,
        ?string $errorMsg = null
    ): void {
        try {
            $this->shipmentDataService->updateShipmentStatusAndTracking($entityId, $status, $shipmentId, $errorMsg);
        } catch (Throwable $e) {
            $this->logger->error('Failed to update shipment status: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Fallback update status in separate connection if main transaction rolled back.
     *
     * @param int $entityId
     * @param string $status
     * @param string $shipmentId
     * @param string $errorMsg
     *
     * @return void
     */
    private function updateStatusFallback(
        int $entityId,
        string $status,
        string $shipmentId,
        string $errorMsg
    ): void {
        try {
            $this->shipmentDataService->updateShipmentStatusAndTracking($entityId, $status, $shipmentId, $errorMsg);
        } catch (Throwable $e) {
            $this->logger->critical(sprintf(
                '[DHL Fallback Update Failed] Entity #%d, Status: %s, Tracking: %s: %s',
                $entityId,
                $status,
                $shipmentId,
                $e->getMessage()
            ));
        }
    }
}
