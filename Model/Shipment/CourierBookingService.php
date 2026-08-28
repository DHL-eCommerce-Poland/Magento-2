<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CourierBookingResult;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class CourierBookingService
{
    /**
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Book courier for selected Magento shipment entity IDs or DHL shipment IDs.
     *
     * @param array $ids Array of entity IDs (int) or DHL shipment IDs (string)
     * @param string $pickupDate YYYY-MM-DD format
     * @param string $pickupTimeFrom HH:MM format
     * @param string $pickupTimeTo HH:MM format
     * @param string|null $additionalInfo
     * @param string $environment ('sandbox' or 'production')
     *
     * @return CourierBookingResult
     * @throws LocalizedException
     */
    public function bookCourier(
        array $ids,
        string $pickupDate,
        string $pickupTimeFrom,
        string $pickupTimeTo,
        ?string $additionalInfo = null,
        string $environment = 'sandbox'
    ): CourierBookingResult {
        if (empty($ids)) {
            throw new LocalizedException(__('No shipments selected for courier booking.'));
        }

        $dhlShipmentIds = [];

        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                try {
                    $entity = $this->shipmentDataRepository->getById((int) $id);
                    $dhlId = $entity->getShipmentId();
                    if (!empty($dhlId)) {
                        $dhlShipmentIds[] = (string) $dhlId;
                    }
                } catch (Throwable $e) {
                    $this->logger?->warning(sprintf(
                        '[CourierBookingService] Could not load shipment entity #%s: %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            } elseif (is_string($id) && trim($id) !== '') {
                $dhlShipmentIds[] = trim($id);
            }
        }

        $dhlShipmentIds = array_values(array_unique($dhlShipmentIds));

        if (empty($dhlShipmentIds)) {
            throw new LocalizedException(
                __('None of the selected shipments have valid DHL Shipment IDs for courier booking.')
            );
        }

        $credentials = $this->credentialsProvider->getCredentials($environment);

        $result = $this->client->bookCourier(
            $credentials,
            $dhlShipmentIds,
            $pickupDate,
            $pickupTimeFrom,
            $pickupTimeTo,
            $additionalInfo
        );

        if ($result->isSuccess()) {
            $bookedStatus = $result->getOrderStatus() ?: (string) __('Kurier zamówiony');
            $courierOrderId = $result->getCourierOrderId();

            foreach ($ids as $id) {
                if (is_numeric($id) && (int) $id > 0) {
                    try {
                        $entity = $this->shipmentDataRepository->getById((int) $id);
                        $entity->setStatus($bookedStatus);
                        if (!empty($courierOrderId)) {
                            $entity->setDispatchNumber($courierOrderId);
                        }
                        $this->shipmentDataRepository->save($entity);
                    } catch (Throwable $e) {
                        $this->logger?->warning(sprintf(
                            '[CourierBookingService] Failed to update status for shipment entity #%s: %s',
                            $id,
                            $e->getMessage()
                        ));
                    }
                }
            }
        }

        return $result;
    }
}
