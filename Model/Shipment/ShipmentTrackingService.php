<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\TrackingResult;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class ShipmentTrackingService
{
    private const XML_PATH_SANDBOX = 'carriers/dhlparcel/sandbox';

    /**
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Fetch tracking information from DHL WebAPI2 and update shipment status in Magento DB.
     *
     * @param int $entityId
     *
     * @return TrackingResult
     * @throws LocalizedException
     */
    public function updateStatus(int $entityId): TrackingResult
    {
        $shipment = $this->shipmentDataRepository->getById($entityId);
        $shipmentId = $shipment->getShipmentId();

        if (empty($shipmentId)) {
            throw new LocalizedException(
                __('No DHL shipment ID available for this shipment entity.')
            );
        }

        $isSandbox = (bool) $this->scopeConfig->getValue(self::XML_PATH_SANDBOX);
        $environment = $isSandbox ? 'sandbox' : 'production';
        $credentials = $this->credentialsProvider->getCredentials($environment);

        $result = $this->client->getTrackAndTraceInfo($credentials, $shipmentId);

        if ($result->isSuccess()) {
            $latestStatus = $result->getLatestStatusDescription();
            if (!empty($latestStatus)) {
                $shipment->setStatus($latestStatus);
            }

            $rawPayload = $shipment->getShipmentData();
            $payload = [];
            if (!empty($rawPayload) && is_string($rawPayload)) {
                try {
                    $payload = (array) json_decode($rawPayload, true);
                } catch (Throwable) {
                    $payload = [];
                }
            }

            $payload['trackingEvents'] = $result->getEvents();
            $shipment->setShipmentData((string) json_encode($payload));
            $this->shipmentDataRepository->save($shipment);
        } else {
            $this->logger?->warning(sprintf(
                '[DHL] Failed to update tracking status for shipment #%s (entity #%d): %s',
                $shipmentId,
                $entityId,
                $result->getErrorMessage() ?? 'Unknown error'
            ));
        }

        return $result;
    }

    /**
     * Batch update status for multiple shipment entity IDs.
     *
     * @param int[] $entityIds
     *
     * @return array{success: array<int, string>, errors: array<int, string>}
     */
    public function massUpdateStatus(array $entityIds): array
    {
        $summary = [
            'success' => [],
            'errors' => [],
        ];

        foreach ($entityIds as $id) {
            try {
                $result = $this->updateStatus((int) $id);
                if ($result->isSuccess()) {
                    $statusText = $result->getLatestStatusDescription() ?? (string) __('Status retrieved');
                    $summary['success'][$id] = $statusText;
                } else {
                    $summary['errors'][$id] = $result->getErrorMessage() ?? (string) __('Tracking request failed.');
                }
            } catch (Throwable $e) {
                $summary['errors'][$id] = $e->getMessage();
            }
        }

        return $summary;
    }
}
