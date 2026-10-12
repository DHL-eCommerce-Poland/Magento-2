<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class ShipmentDeletionService
{
    /**
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param MagentoShipmentSyncService $magentoShipmentSyncService
     * @param LoggerInterface $logger
     * @param ShipmentDataPayloadReader $payloadReader
     */
    public function __construct(
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly MagentoShipmentSyncService $magentoShipmentSyncService,
        private readonly LoggerInterface $logger,
        private readonly ShipmentDataPayloadReader $payloadReader
    ) {
    }

    /**
     * Delete shipment from DHL WebAPI2 and remove entity from Magento DB.
     *
     * @param int $entityId
     *
     * @return ShipmentDeletionResult
     * @throws LocalizedException
     */
    public function deleteShipment(int $entityId): ShipmentDeletionResult
    {
        $shipment = $this->shipmentDataRepository->getById($entityId);
        $shipmentId = $shipment->getShipmentId();

        if (!empty($shipmentId)) {
            $credentials = $this->credentialsProvider->getCredentials();

            $returnShipmentIds = $this->payloadReader->getReturnShipmentIdsFromEntity($shipment);
            $result = $this->client->deleteShipment($credentials, $shipmentId, $returnShipmentIds);

            if (!$result->isSuccess()) {
                $errorMsg = $result->getErrorMessage() ?? __('DHL API returned an error during deletion.')->render();
                return new ShipmentDeletionResult(false, $errorMsg);
            }
        }

        $magentoShipmentId = $shipment->getMagentoShipmentId();
        if ($this->magentoShipmentSyncService && $magentoShipmentId) {
            try {
                $this->magentoShipmentSyncService->cancelDhlTracking((int)$magentoShipmentId);
            } catch (Throwable $e) {
                $this->logger->error(sprintf(
                    '[DHL] Failed to cancel tracking on Magento shipment #%d: %s',
                    $magentoShipmentId,
                    $e->getMessage()
                ));
            }
        }

        $isDeleted = $this->shipmentDataRepository->delete($shipment);

        return new ShipmentDeletionResult(
            $isDeleted,
            $isDeleted ? null : (string) __('Shipment could not be deleted from Magento.')
        );
    }
}
