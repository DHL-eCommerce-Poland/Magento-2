<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CourierBookingResult;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class CourierBookingService
{
    /**
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param TimezoneInterface $timezone
     * @param ShipmentDateSanitizer $dateSanitizer
     * @param LoggerInterface $logger
     * @param ShipmentDataPayloadReader $payloadReader
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly TimezoneInterface $timezone,
        private readonly ShipmentDateSanitizer $dateSanitizer,
        private readonly LoggerInterface $logger,
        private readonly ShipmentDataPayloadReader $payloadReader
    ) {
    }

    /**
     * Book courier for selected Magento shipment entities.
     *
     * @param array $ids
     * @param string $pickupDate YYYY-MM-DD format
     * @param string $pickupTimeFrom HH:MM format
     * @param string $pickupTimeTo HH:MM format
     * @param string|null $additionalInfo
     *
     * @return CourierBookingResult
     * @throws LocalizedException
     */
    public function bookCourier(
        array $ids,
        string $pickupDate,
        string $pickupTimeFrom,
        string $pickupTimeTo,
        ?string $additionalInfo = null
    ): CourierBookingResult {
        if (empty($ids)) {
            throw new LocalizedException(__('No shipments selected for courier booking.'));
        }

        try {
            $formattedPickupDate = $this->dateSanitizer->sanitize($pickupDate, null, false);
        } catch (Throwable $e) {
            $formattedPickupDate = $this->timezone->scopeDate()->format('Y-m-d');
            $this->logger->warning(sprintf(
                '[CourierBookingService] Could not parse pickup date "%s": %s',
                $pickupDate,
                $e->getMessage()
            ));
        }

        $dhlShipmentIds = [];
        $entityIdsByDhlShipmentId = [];
        $shipmentIdsByServiceType = [];
        $serviceTypeByDhlShipmentId = [];

        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                try {
                    $entity = $this->shipmentDataRepository->getById((int) $id);
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[CourierBookingService] Could not load shipment entity #%s: %s',
                        $id,
                        $e->getMessage()
                    ), ['exception' => $e]);
                    throw new LocalizedException(
                        __('Could not load selected shipment #%1 to determine its DHL service code.', (int) $id)
                    );
                }

                $serviceType = $this->payloadReader->getServiceTypeFromEntity($entity);
                if ($serviceType === null) {
                    throw new LocalizedException(
                        __('Cannot determine DHL service code for selected shipment #%1.', (int) $id)
                    );
                }

                $dhlId = $entity->getShipmentId();
                if (empty($dhlId)) {
                    continue;
                }

                $dhlId = (string) $dhlId;
                $hasConflict = isset($serviceTypeByDhlShipmentId[$dhlId])
                    && $serviceTypeByDhlShipmentId[$dhlId] !== $serviceType;
                if ($hasConflict) {
                    throw new LocalizedException(
                        __('Selected DHL shipment %1 has conflicting service codes.', $dhlId)
                    );
                }

                if (!isset($serviceTypeByDhlShipmentId[$dhlId])) {
                    $serviceTypeByDhlShipmentId[$dhlId] = $serviceType;
                    $shipmentIdsByServiceType[$serviceType][] = $dhlId;
                    $dhlShipmentIds[] = $dhlId;
                }

                $entityIdsByDhlShipmentId[$dhlId][] = (int) $id;
            } elseif (is_string($id) && trim($id) !== '') {
                throw new LocalizedException(
                    __('Courier booking requires shipment entities to determine DHL service codes.')
                );
            }
        }

        if (empty($dhlShipmentIds)) {
            throw new LocalizedException(
                __('None of the selected shipments have valid DHL Shipment IDs for courier booking.')
            );
        }

        $credentials = $this->credentialsProvider->getCredentials();

        $result = $this->client->bookCourier(
            $credentials,
            $dhlShipmentIds,
            $formattedPickupDate,
            $pickupTimeFrom,
            $pickupTimeTo,
            $additionalInfo
        );

        if ($result->isSuccess()) {
            $courierOrderIds = $result->getCourierOrderIds();
            if (count($courierOrderIds) !== count($shipmentIdsByServiceType)) {
                return new CourierBookingResult(
                    false,
                    [],
                    'INVALID_RESPONSE',
                    (string) __('DHL returned an unexpected number of courier order IDs.')
                );
            }

            foreach ($courierOrderIds as $courierOrderId) {
                if (!is_string($courierOrderId) || trim($courierOrderId) === '') {
                    return new CourierBookingResult(
                        false,
                        [],
                        'INVALID_RESPONSE',
                        (string) __('DHL returned an invalid courier order ID.')
                    );
                }
            }

            $bookedStatus = $result->getOrderStatus() ?: (string) __('Kurier zamówiony');

            foreach (array_values($shipmentIdsByServiceType) as $index => $serviceShipmentIds) {
                $courierOrderId = trim($courierOrderIds[$index]);
                foreach ($serviceShipmentIds as $dhlShipmentId) {
                    foreach (array_unique($entityIdsByDhlShipmentId[$dhlShipmentId] ?? []) as $entityId) {
                        try {
                            $entity = $this->shipmentDataRepository->getById($entityId);
                            $entity->setStatus($bookedStatus);
                            $entity->setDispatchNumber($courierOrderId);
                            $this->shipmentDataRepository->save($entity);
                        } catch (Throwable $e) {
                            $this->logger->warning(sprintf(
                                '[CourierBookingService] Failed to update courier booking for shipment entity #%s: %s',
                                $entityId,
                                $e->getMessage()
                            ));
                        }
                    }
                }
            }
        }

        return $result;
    }
}
