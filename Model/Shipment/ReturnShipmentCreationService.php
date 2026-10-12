<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use Exception;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CreateShipmentReturnResult;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use DhlPoland\Dhl\Model\Api\ShipmentReturnDataMapper;
use DhlPoland\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class ReturnShipmentCreationService
{
    private const LOCK_TTL = 15;

    /**
     * @param ShipmentDataService $shipmentDataService
     * @param ShipmentDataValidator $validator
     * @param ShipmentReturnDataMapper $returnMapper
     * @param CredentialsProvider $credentialsProvider
     * @param ClientInterface $client
     * @param LockManagerInterface $lockManager
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly ShipmentDataService $shipmentDataService,
        private readonly ShipmentDataValidator $validator,
        private readonly ShipmentReturnDataMapper $returnMapper,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ClientInterface $client,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Validate and create return shipment directly in DHL WebAPI2 via createShipmentReturn.
     *
     * Saves return shipment entity to DB only upon API success.
     *
     * @param DhlShipmentData $returnShipmentData
     * @param int $expirationDays
     * @param string|null $primaryWaybillNumber
     * @param int|null $originalEntityId
     *
     * @return CreateShipmentReturnResult
     * @throws LocalizedException
     */
    public function createReturnShipment(
        DhlShipmentData $returnShipmentData,
        int $expirationDays = ShipmentSettingsData::DEFAULT_RETURN_LABEL_EXP_DAYS,
        ?string $primaryWaybillNumber = null,
        ?int $originalEntityId = null
    ): CreateShipmentReturnResult {
        $orderId = $returnShipmentData->getOrderId();
        $hash = hash('sha256', $this->serializer->serialize($returnShipmentData->toArray()));
        $lockName = 'dhl_return_shipment_create_' . ($orderId ?? $hash);

        if (!$this->lockManager->lock($lockName, self::LOCK_TTL)) {
            throw new LocalizedException(
                __('Return shipment creation is already in progress. Please wait a moment.')
            );
        }

        try {
            // 1. Validate payload FIRST
            $this->validator->validate($returnShipmentData);

            // 2. Prepare credentials and map payload for createShipmentReturn
            $credentials = $this->credentialsProvider->getCredentials();
            $mappedData = $this->returnMapper->map($returnShipmentData, $expirationDays, $primaryWaybillNumber);

            // 3. Dispatch to DHL WebAPI2 (createShipmentReturn)
            $apiResult = $this->client->createShipmentReturn($credentials, $mappedData, $orderId);

            // 4. Handle Failure scenario (do NOT save DB entity if failed or timeout)
            if ($apiResult->isTimeout() || !$apiResult->isSuccess()) {
                return $apiResult;
            }

            // 5. Handle API Success scenario (update return_shipment_id on original shipment entity)
            $waybill = $apiResult->getWaybill() ?: $apiResult->getShipmentId();

            if ($originalEntityId !== null && $waybill !== null) {
                try {
                    $this->shipmentDataService->updateReturnShipmentId(
                        $originalEntityId,
                        $waybill,
                        $apiResult->getItems()
                    );
                } catch (Exception $e) {
                    $this->logger->warning(
                        'Failed to update return_shipment_id on original shipment entity #' . $originalEntityId,
                        ['exception' => $e]
                    );
                }
            }

            return $apiResult;
        } finally {
            if (!$this->lockManager->unlock($lockName)) {
                $this->logger->warning(sprintf('[ReturnShipmentCreation] Failed to release lock: %s', $lockName));
            }
        }
    }
}
