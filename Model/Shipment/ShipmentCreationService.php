<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\CreateShipmentsResult;
use Ideo\Dhl\Model\Api\ShipmentFullDataMapper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ShipmentCreationService
{
    private const LOCK_TTL = 15;

    /**
     * @param ShipmentDataService $shipmentDataService
     * @param ShipmentDataValidator $validator
     * @param ShipmentFullDataMapper $mapper
     * @param CredentialsProvider $credentialsProvider
     * @param ClientInterface $client
     * @param LockManagerInterface $lockManager
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
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Validate and create shipment directly in DHL WebAPI2 without saving preliminary draft.
     *
     * Saves to DB only upon API success (status CREATED or DHL order status).
     *
     * @param DhlShipmentData $shipmentData
     * @param string $environment ('sandbox' or 'production')
     * @param string|null $presetSenderId
     * @param string|null $presetShipmentId
     *
     * @return CreateShipmentsResult
     * @throws LocalizedException
     */
    public function createShipment(
        DhlShipmentData $shipmentData,
        string $environment = 'sandbox',
        ?string $presetSenderId = null,
        ?string $presetShipmentId = null
    ): CreateShipmentsResult {
        $orderId = $shipmentData->getOrderId();
        $hash = hash('sha256', $this->serializer->serialize($shipmentData->toArray()));
        $lockName = 'dhl_shipment_create_' . ($orderId ?? $hash);

        if (!$this->lockManager->lock($lockName, self::LOCK_TTL)) {
            throw new LocalizedException(
                __('Shipment creation is already in progress. Please wait a moment.')
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

            // 4. Handle Failure or Timeout scenario (do NOT save anything in DB)
            if ($apiResult->isTimeout() || !$apiResult->isSuccess()) {
                return $apiResult;
            }

            // 5. Handle API Success scenario (save DB entity with DHL order status or CREATED fallback)
            $status = $apiResult->getOrderStatus() ?: ShipmentDataInterface::DEFAULT_STATUS;
            $this->shipmentDataService->saveShipmentDataWithStatus(
                $shipmentData,
                $status,
                null,
                $presetSenderId,
                $presetShipmentId,
                $apiResult->getShipmentId(),
                null,
                $apiResult->getPackageNumbers()
            );

            return $apiResult;
        } finally {
            if (!$this->lockManager->unlock($lockName)) {
                $this->logger->warning(sprintf('[ShipmentCreation] Failed to release lock: %s', $lockName));
            }
        }
    }

    /**
     * Re-create shipment: deletes old DHL shipment (if exists), creates updated data, and updates entity in DB.
     *
     * @param int $entityId
     * @param DhlShipmentData $shipmentData
     * @param string $environment ('sandbox' or 'production')
     *
     * @return CreateShipmentsResult
     * @throws LocalizedException
     */
    public function recreateShipment(
        int $entityId,
        DhlShipmentData $shipmentData,
        string $environment = 'sandbox'
    ): CreateShipmentsResult {
        $lockName = 'dhl_shipment_recreate_' . $entityId;

        if (!$this->lockManager->lock($lockName, self::LOCK_TTL)) {
            throw new LocalizedException(
                __('Shipment re-creation is already in progress. Please wait a moment.')
            );
        }

        try {
            $this->validator->validate($shipmentData);
            $credentials = $this->credentialsProvider->getCredentials($environment);

            $existingEntity = $this->shipmentDataService->getShipmentEntity($entityId);
            $oldShipmentId = $existingEntity->getShipmentId();

            if (!empty($oldShipmentId)) {
                try {
                    $this->client->deleteShipment($credentials, $oldShipmentId);
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[DHL Recreate] Failed to delete old DHL shipment %s for entity #%d before recreation: %s',
                        $oldShipmentId,
                        $entityId,
                        $e->getMessage()
                    ));
                }
            }

            $mappedData = $this->mapper->map($shipmentData);
            $apiResult = $this->client->createShipments($credentials, [$mappedData], $shipmentData->getOrderId());

            if ($apiResult->isTimeout() || !$apiResult->isSuccess()) {
                return $apiResult;
            }

            $newShipmentId = (string) $apiResult->getShipmentId();
            $status = $apiResult->getOrderStatus() ?: ShipmentDataInterface::DEFAULT_STATUS;
            $this->shipmentDataService->updateExistingShipmentPayload(
                $entityId,
                $shipmentData,
                $status,
                $newShipmentId,
                null,
                $apiResult->getPackageNumbers()
            );

            return $apiResult;
        } finally {
            if (!$this->lockManager->unlock($lockName)) {
                $this->logger->warning(sprintf('[ShipmentCreation] Failed to release lock: %s', $lockName));
            }
        }
    }
}
