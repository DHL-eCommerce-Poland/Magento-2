<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CourierBookingResult;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use DhlPoland\Dhl\Model\Config\Source\PackageType;
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
     * @param int[]|string[] $ids
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
        $shipmentIdsByGroup = [];
        $groupByDhlShipmentId = [];

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
                $isPallet = $this->payloadReader->hasPalletFromEntity($entity);
                $groupKey = $serviceType . '_' . ($isPallet ? PackageType::PALLET : PackageType::PACKAGE);

                $hasConflict = isset($groupByDhlShipmentId[$dhlId])
                    && $groupByDhlShipmentId[$dhlId] !== $groupKey;
                if ($hasConflict) {
                    throw new LocalizedException(
                        __('Selected DHL shipment %1 has conflicting service codes.', $dhlId)
                    );
                }

                if (!isset($groupByDhlShipmentId[$dhlId])) {
                    $groupByDhlShipmentId[$dhlId] = $groupKey;
                    $shipmentIdsByGroup[$groupKey][] = $dhlId;
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
        $successfulOrderIds = [];
        $failedErrors = [];
        $firstErrorCode = null;
        $bookedStatus = null;

        foreach ($shipmentIdsByGroup as $groupKey => $groupShipmentIds) {
            $result = $this->client->bookCourier(
                $credentials,
                $groupShipmentIds,
                $formattedPickupDate,
                $pickupTimeFrom,
                $pickupTimeTo,
                $additionalInfo
            );

            if (!$result->isSuccess()) {
                $errMsg = $result->getErrorMessage()
                    ?: (string) __('Failed to book courier for group %1.', $groupKey);
                $failedErrors[] = $errMsg;
                if ($firstErrorCode === null) {
                    $firstErrorCode = $result->getErrorCode() ?: 'BOOK_COURIER_ERROR';
                }
                $this->logger->warning(sprintf(
                    '[CourierBookingService] Failed to book courier for group %s: [%s] %s',
                    $groupKey,
                    $result->getErrorCode() ?? 'UNKNOWN',
                    $errMsg
                ));
                continue;
            }

            $courierOrderIds = $result->getCourierOrderIds();
            $validOrderIds = [];
            foreach ($courierOrderIds as $orderId) {
                if (is_string($orderId) && trim($orderId) !== '') {
                    $validOrderIds[] = trim($orderId);
                }
            }

            if (empty($validOrderIds)) {
                $errMsg = (string) __('DHL returned an invalid courier order ID for group %1.', $groupKey);
                $failedErrors[] = $errMsg;
                if ($firstErrorCode === null) {
                    $firstErrorCode = 'INVALID_RESPONSE';
                }
                $this->logger->warning(sprintf(
                    '[CourierBookingService] DHL returned empty courier order IDs for group %s.',
                    $groupKey
                ));
                continue;
            }

            if (count($validOrderIds) > 1) {
                $this->logger->info(sprintf(
                    '[CourierBookingService] DHL returned multiple courier order IDs for group %s: %s',
                    $groupKey,
                    implode(', ', $validOrderIds)
                ));
            }

            if ($bookedStatus === null) {
                $bookedStatus = $result->getOrderStatus() ?: (string) __('Kurier zamówiony');
            }

            $primaryOrderId = $validOrderIds[0];
            foreach ($validOrderIds as $orderId) {
                if (!in_array($orderId, $successfulOrderIds, true)) {
                    $successfulOrderIds[] = $orderId;
                }
            }

            foreach ($groupShipmentIds as $dhlShipmentId) {
                foreach (array_unique($entityIdsByDhlShipmentId[$dhlShipmentId] ?? []) as $entityId) {
                    try {
                        $entity = $this->shipmentDataRepository->getById($entityId);
                        $entity->setStatus($bookedStatus);
                        $entity->setDispatchNumber($primaryOrderId);
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

        if (!empty($failedErrors)) {
            if (empty($successfulOrderIds)) {
                return new CourierBookingResult(
                    false,
                    [],
                    $firstErrorCode ?? 'BOOK_COURIER_ERROR',
                    implode('; ', $failedErrors)
                );
            }

            return new CourierBookingResult(
                false,
                $successfulOrderIds,
                'PARTIAL_BOOKING_ERROR',
                (string) __(
                    'Booked courier orders: %1. Errors: %2',
                    implode(', ', $successfulOrderIds),
                    implode('; ', $failedErrors)
                ),
                $bookedStatus
            );
        }

        return new CourierBookingResult(
            true,
            $successfulOrderIds,
            null,
            null,
            $bookedStatus
        );
    }
}
