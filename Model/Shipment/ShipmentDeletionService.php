<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class ShipmentDeletionService
{
    private const XML_PATH_SANDBOX = 'carriers/dhlpoland/sandbox';

    /**
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param MagentoShipmentSyncService $magentoShipmentSyncService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly MagentoShipmentSyncService $magentoShipmentSyncService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Delete shipment from DHL WebAPI2 and remove entity from Magento DB.
     *
     * @param int $entityId
     *
     * @return bool
     * @throws LocalizedException
     */
    public function deleteShipment(int $entityId): bool
    {
        $shipment = $this->shipmentDataRepository->getById($entityId);
        $shipmentId = $shipment->getShipmentId();

        if (!empty($shipmentId)) {
            $isSandbox = (bool)$this->scopeConfig->getValue(self::XML_PATH_SANDBOX);
            $environment = $isSandbox ? 'sandbox' : 'production';
            $credentials = $this->credentialsProvider->getCredentials($environment);

            $result = $this->client->deleteShipment($credentials, $shipmentId);

            if (!$result->isSuccess()) {
                $errorMsg = $result->getErrorMessage() ?? __('DHL API returned an error during deletion.')->render();
                $this->logger->error(sprintf(
                    '[DHL] Failed to delete shipment #%s (entity #%d) in DHL API: %s',
                    $shipmentId,
                    $entityId,
                    $errorMsg
                ));

                throw new LocalizedException(__('Failed to delete shipment in DHL: %1', $errorMsg));
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

        return $this->shipmentDataRepository->delete($shipment);
    }
}
