<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CreateShipmentsResult;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\ShipmentFullDataMapper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
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
     * @param ReturnShipmentDataBuilder $returnBuilder
     * @param ReturnShipmentCreationService $returnCreationService
     * @param MagentoShipmentSyncService $magentoShipmentSyncService
     */
    public function __construct(
        private readonly ShipmentDataService $shipmentDataService,
        private readonly ShipmentDataValidator $validator,
        private readonly ShipmentFullDataMapper $mapper,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ClientInterface $client,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly ReturnShipmentDataBuilder $returnBuilder,
        private readonly ReturnShipmentCreationService $returnCreationService,
        private readonly MagentoShipmentSyncService $magentoShipmentSyncService
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

            $packageNumbers = $apiResult->getPackageNumbers()
                ?: ($apiResult->getShipmentId() ? [(string)$apiResult->getShipmentId()] : []);

            // 5. Create native Magento shipment if sync service is available
            $magentoShipmentId = null;
            if ($this->magentoShipmentSyncService && $orderId !== null) {
                try {
                    $itemsQty = $shipmentData->getItemsQty();
                    $magentoShipment = $this->magentoShipmentSyncService->createMagentoShipment(
                        (int)$orderId,
                        $packageNumbers,
                        $itemsQty
                    );
                    $magentoShipmentId = (int)$magentoShipment->getEntityId();
                } catch (Throwable $e) {
                    $this->logger->error(sprintf(
                        '[ShipmentCreation] Failed to create native Magento shipment for order #%d: %s',
                        $orderId,
                        $e->getMessage()
                    ));
                }
            }

            // 6. Handle API Success scenario (save DB entity with DHL order status or CREATED fallback)
            $status = $apiResult->getOrderStatus() ?: ShipmentDataInterface::DEFAULT_STATUS;
            $savedEntity = $this->shipmentDataService->saveShipmentDataWithStatus(
                $shipmentData,
                $status,
                null,
                $presetSenderId,
                $presetShipmentId,
                $apiResult->getShipmentId(),
                null,
                $packageNumbers,
                $magentoShipmentId
            );

            // 7. Auto-create return shipment if requested
            if ($shipmentData->getShipmentSettings()->autoCreateReturnShipment()
                && $apiResult->getShipmentId()
            ) {
                $this->logger->info(sprintf(
                    '[ShipmentCreation] Triggering auto return shipment creation for order #%s, primary waybill #%s',
                    (string) $orderId,
                    (string) $apiResult->getShipmentId()
                ));
                try {
                    $returnShipmentData = $this->returnBuilder->buildReturnShipmentData($shipmentData);
                    $expDays = $shipmentData->getShipmentSettings()->getReturnLabelExpDays() ?: 14;
                    $savedEntityId = $savedEntity ? (int) $savedEntity->getEntityId() : null;

                    $this->returnCreationService->createReturnShipment(
                        returnShipmentData: $returnShipmentData,
                        environment: $environment,
                        expirationDays: $expDays,
                        primaryWaybillNumber: (string) $apiResult->getShipmentId(),
                        originalEntityId: $savedEntityId
                    );
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[ShipmentCreation] Auto return shipment creation failed for order #%s: %s',
                        (string) $orderId,
                        $e->getMessage()
                    ), ['exception' => $e]);
                }
            }

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

            $packageNumbers = $apiResult->getPackageNumbers()
                ?: ($newShipmentId !== '' ? [$newShipmentId] : []);

            // Update tracks on existing Magento shipment
            $magentoShipmentId = $existingEntity->getMagentoShipmentId();
            if ($this->magentoShipmentSyncService && $magentoShipmentId) {
                try {
                    $this->magentoShipmentSyncService->updateMagentoShipmentTracks(
                        $magentoShipmentId,
                        $packageNumbers
                    );
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[DHL Recreate] Failed to update native Magento shipment tracks #%d: %s',
                        $magentoShipmentId,
                        $e->getMessage()
                    ));
                }
            }

            $this->shipmentDataService->updateExistingShipmentPayload(
                $entityId,
                $shipmentData,
                $status,
                $newShipmentId,
                null,
                $packageNumbers,
                $magentoShipmentId
            );

            return $apiResult;
        } finally {
            if (!$this->lockManager->unlock($lockName)) {
                $this->logger->warning(sprintf('[ShipmentCreation] Failed to release lock: %s', $lockName));
            }
        }
    }
}
