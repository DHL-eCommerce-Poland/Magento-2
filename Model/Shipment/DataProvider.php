<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use DhlPoland\Dhl\Model\Shipment\Data\CustomsData;
use DhlPoland\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use DhlPoland\Dhl\Model\Shipment\Data\SpecialServicesData;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Psr\Log\LoggerInterface;
use Throwable;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param SerializerInterface $serializer
     * @param TimezoneInterface $timezone
     * @param ShipmentDateSanitizer $dateSanitizer
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly SerializerInterface $serializer,
        private readonly TimezoneInterface $timezone,
        private readonly ShipmentDateSanitizer $dateSanitizer,
        private readonly LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * Get data for DHL shipment form binding.
     *
     * @return array
     */
    // phpcs:ignore Generic.Metrics.NestingLevel.TooHigh
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $item) {
            $data = $item->getData();
            $rawPayload = $item->getShipmentData();
            $orderGrandTotal = null;

            if (!empty($rawPayload) && is_string($rawPayload)) {
                try {
                    $payload = $this->serializer->unserialize($rawPayload);
                } catch (Throwable $e) {
                    $payload = null;
                    $this->logger->error(sprintf(
                        '[DataProvider] Failed to unserialize payload for shipment entity #%s: %s',
                        $item->getId(),
                        $e->getMessage()
                    ));
                }

                if (is_array($payload)) {
                    if (isset($payload['orderGrandTotal']) && is_numeric($payload['orderGrandTotal'])) {
                        $orderGrandTotal = (float) $payload['orderGrandTotal'];
                    }
                    if (isset($payload['sender']) && is_array($payload['sender'])) {
                        $data['sender'] = $payload['sender'];
                    }
                    if (isset($payload['recipient']) && is_array($payload['recipient'])) {
                        $data['recipient'] = $payload['recipient'];
                    }
                    if (isset($payload['shipmentSettings']) && is_array($payload['shipmentSettings'])) {
                        try {
                            $hasNoPkg = empty($payload['shipmentSettings']['packageNumbers'])
                                && !empty($payload['packageNumbers']);
                            if ($hasNoPkg) {
                                $payload['shipmentSettings']['packageNumbers'] = $payload['packageNumbers'];
                            }
                            $shipmentId = (string) ($item->getShipmentId() ?? ($payload['shipmentId'] ?? ''));
                            $status = (string) ($item->getStatus() ?? '');
                            $createdAt = (string) ($item->getCreatedAt() ?? '');
                            $dispatchNumber = (string) ($item->getDispatchNumber() ?? '');
                            $returnShipmentId = (string) ($item->getReturnShipmentId() ?? '');
                            $data['shipmentSettings'] = $this->normalizeShipmentSettings(
                                $payload['shipmentSettings'],
                                $shipmentId,
                                $status,
                                $createdAt,
                                $payload['sender'] ?? [],
                                $dispatchNumber,
                                $returnShipmentId
                            );
                            $hasPackages = isset($payload['shipmentSettings']['packages'])
                                && is_array($payload['shipmentSettings']['packages']);
                            if ($hasPackages) {
                                $pkgNumsRaw = $payload['packageNumbers']
                                    ?? ($payload['shipmentSettings']['packageNumbers'] ?? []);
                                $rootPkgNums = is_array($pkgNumsRaw)
                                    ? $pkgNumsRaw
                                    : (is_string($pkgNumsRaw) ? explode(',', $pkgNumsRaw) : []);
                                $rootReturnIds = array_values(array_filter(
                                    array_map('trim', explode(',', $returnShipmentId)),
                                    static fn($val) => $val !== ''
                                ));
                                $data['packages'] = $this->normalizePackages(
                                    $payload['shipmentSettings']['packages'],
                                    $rootPkgNums,
                                    $rootReturnIds
                                );
                                $data['packages_fieldset'] = ['packages' => $data['packages']];
                            }
                        } catch (Throwable $e) {
                            $this->logger->error(sprintf(
                                '[DataProvider] Failed to normalize shipmentSettings for shipment entity #%s: %s',
                                $item->getId(),
                                $e->getMessage()
                            ));
                        }
                    }
                    if (isset($payload['specialServices']) && is_array($payload['specialServices'])) {
                        try {
                            $data['specialServices'] = $this->normalizeSpecialServices($payload['specialServices']);
                            $data['special_services'] = ['specialServices' => $data['specialServices']];
                        } catch (Throwable $e) {
                            $this->logger->error(sprintf(
                                '[DataProvider] Failed to normalize specialServices for shipment entity #%s: %s',
                                $item->getId(),
                                $e->getMessage()
                            ));
                        }
                    }
                    if (isset($payload['customs']) && is_array($payload['customs'])) {
                        try {
                            $data['customs'] = $this->normalizeCustoms($payload['customs']);
                            $data['customs_fieldset'] = $data['customs'];
                        } catch (Throwable $e) {
                            $this->logger->error(sprintf(
                                '[DataProvider] Failed to normalize customs for shipment entity #%s: %s',
                                $item->getId(),
                                $e->getMessage()
                            ));
                        }
                    }
                    $hasRootPackages = !isset($data['packages'])
                        && isset($payload['packages'])
                        && is_array($payload['packages']);
                    if ($hasRootPackages) {
                        try {
                            $pkgNumsRaw = $payload['packageNumbers']
                                ?? ($payload['shipmentSettings']['packageNumbers'] ?? []);
                            $rootPkgNums = is_array($pkgNumsRaw)
                                ? $pkgNumsRaw
                                : (is_string($pkgNumsRaw) ? explode(',', $pkgNumsRaw) : []);
                            $rootReturnIds = array_values(array_filter(
                                array_map('trim', explode(',', (string)($item->getReturnShipmentId() ?? ''))),
                                static fn($val) => $val !== ''
                            ));
                            $data['packages'] = $this->normalizePackages(
                                $payload['packages'],
                                $rootPkgNums,
                                $rootReturnIds
                            );
                            $data['packages_fieldset'] = ['packages' => $data['packages']];
                        } catch (Throwable $e) {
                            $this->logger->error(sprintf(
                                '[DataProvider] Failed to normalize root packages for shipment entity #%s: %s',
                                $item->getId(),
                                $e->getMessage()
                            ));
                        }
                    }
                }
            }

            if ($orderGrandTotal === null && $item->getOrderId()) {
                try {
                    $order = $this->orderRepository->get((int)$item->getOrderId());
                    $orderGrandTotal = (float)$order->getGrandTotal();
                } catch (Throwable $e) {
                    $this->logger->error(sprintf(
                        '[DataProvider] Failed to load order #%s for grand total: %s',
                        $item->getOrderId(),
                        $e->getMessage()
                    ));
                }
            }

            if ($orderGrandTotal !== null) {
                $formattedTotal = number_format($orderGrandTotal, 2, '.', '');
                $data['orderGrandTotal'] = $formattedTotal;
                if (!isset($data['specialServices']) || !is_array($data['specialServices'])) {
                    $data['specialServices'] = [];
                }
                $data['specialServices']['orderGrandTotal'] = $formattedTotal;
                if (!isset($data['special_services']) || !is_array($data['special_services'])) {
                    $data['special_services'] = [];
                }
                $data['special_services']['specialServices'] = $data['specialServices'];
            }

            $this->loadedData[$item->getId()] = $data;
        }

        return $this->loadedData;
    }

    /**
     * Normalize special services array so boolean flags are converted to '1'/'0' for UI component checkboxes.
     *
     * @param array $ss
     *
     * @return array
     */
    private function normalizeSpecialServices(array $ss): array
    {
        $dto = SpecialServicesData::fromArray($ss);
        $isEvening = $dto->isDeliveryEvening();

        return [
            'insurance' => $dto->isInsurance() ? '1' : '0',
            'insuranceValue' => $dto->isInsurance() ? $dto->getInsuranceValue() : null,
            'collectOnDelivery' => $dto->isCollectOnDelivery() ? '1' : '0',
            'collectOnDeliveryValue' => $dto->isCollectOnDelivery() ? $dto->getCollectOnDeliveryValue() : null,
            'collectOnDeliveryForm' => $dto->getCollectOnDeliveryForm(),
            'deliveryEvening' => $isEvening ? '1' : '0',
            'deliveryOnSaturday' => $dto->isDeliveryOnSaturday() ? '1' : '0',
            'pickupOnSaturday' => $dto->isPickupOnSaturday() ? '1' : '0',
            'predeliveryInformation' => $dto->isPredeliveryInformation() ? '1' : '0',
            'returnOnDelivery' => $dto->isReturnOnDelivery() ? '1' : '0',
            'returnOnDeliveryReference' => $dto->isReturnOnDelivery() ? $dto->getReturnOnDeliveryReference() : '',
            'proofOfDelivery' => $dto->isProofOfDelivery() ? '1' : '0',
            'ageVer' => $dto->isAgeVer() ? '1' : '0',
        ];
    }

    /**
     * Normalize shipment settings flags to string '1'/'0' and ensure shipmentId and status are present.
     *
     * @param array $settings
     * @param string $shipmentId
     * @param string $status
     * @param string $createdAt
     * @param array $sender
     * @param string $dispatchNumber
     * @param string $returnShipmentId
     * @return array
     */
    private function normalizeShipmentSettings(
        array $settings,
        string $shipmentId = '',
        string $status = '',
        string $createdAt = '',
        array $sender = [],
        string $dispatchNumber = '',
        string $returnShipmentId = ''
    ): array {
        $settings['autoCreateReturnShipment'] = !empty($settings['autoCreateReturnShipment']) ? '1' : '0';

        if (empty($settings['returnLabelExpDays']) || (int)$settings['returnLabelExpDays'] <= 0) {
            $settings['returnLabelExpDays'] = ShipmentSettingsData::DEFAULT_RETURN_LABEL_EXP_DAYS;
        }

        if (empty($settings['returnLabelType'])) {
            $settings['returnLabelType'] = 'BLP';
        }

        if (empty($settings['returnQrCodeType'])) {
            $settings['returnQrCodeType'] = 'QR_PDF';
        }

        if (!isset($settings['returnBookCourier']) || $settings['returnBookCourier'] === '') {
            $settings['returnBookCourier'] = 0;
        }
        if (empty($settings['shipmentId']) && !empty($shipmentId)) {
            $settings['shipmentId'] = $shipmentId;
        }
        if (!empty($status)) {
            $settings['status'] = $this->formatStatusLabel($status);
        }
        if (!empty($dispatchNumber)) {
            $settings['dispatchNumber'] = $dispatchNumber;
        } else {
            $settings['dispatchNumber'] = null;
        }
        if (!empty($returnShipmentId)) {
            $settings['returnShipmentId'] = $returnShipmentId;
        } else {
            $settings['returnShipmentId'] = null;
        }

        $pkgNums = $settings['packageNumbers'] ?? null;
        if (!empty($pkgNums)) {
            if (is_array($pkgNums)) {
                $filtered = array_filter(array_map('trim', $pkgNums));
                $settings['packageNumbers'] = !empty($filtered) ? implode(', ', $filtered) : null;
            } elseif (is_string($pkgNums) && trim($pkgNums) !== '') {
                $settings['packageNumbers'] = trim($pkgNums);
            } else {
                $settings['packageNumbers'] = null;
            }
        } else {
            $settings['packageNumbers'] = null;
        }

        $today = $this->timezone->scopeDate()->format('Y-m-d');
        $rawDate = (string)($settings['shipmentDate'] ?? '');
        $settings['shipmentDate'] = $this->dateSanitizer->sanitize($rawDate, $today);

        if (!empty($settings['pickupHourFrom'])) {
            $settings['pickupHourFrom'] = substr((string)$settings['pickupHourFrom'], 0, 5);
        } else {
            $settings['pickupHourFrom'] = null;
        }

        if (!empty($settings['pickupHourTo'])) {
            $settings['pickupHourTo'] = substr((string)$settings['pickupHourTo'], 0, 5);
        } else {
            $settings['pickupHourTo'] = null;
        }

        unset($settings['packages']);

        return $settings;
    }

    /**
     * Normalize packages array flags to string '1'/'0', record_id, packageNumber, and returnShipmentId.
     *
     * @param array $packages
     * @param array $rootPackageNumbers
     * @param array $rootReturnShipmentIds
     *
     * @return array
     */
    private function normalizePackages(
        array $packages,
        array $rootPackageNumbers = [],
        array $rootReturnShipmentIds = []
    ): array {
        $result = [];
        $cleanRootPkgNums = array_values(array_filter(
            array_map('trim', $rootPackageNumbers),
            static fn($val) => $val !== ''
        ));
        $cleanRootReturnIds = array_values(array_filter(
            array_map('trim', $rootReturnShipmentIds),
            static fn($val) => $val !== ''
        ));

        $pkgNumOffset = 0;
        $retIdOffset = 0;

        foreach ($packages as $index => $pkg) {
            if (!is_array($pkg)) {
                continue;
            }
            $pkg['record_id'] = (string)$index;
            $pkg['isNonStandard'] = !empty($pkg['isNonStandard']) ? '1' : '0';
            $pkg['hasEuroReturn'] = !empty($pkg['hasEuroReturn']) ? '1' : '0';

            $qty = max(1, (int)($pkg['quantity'] ?? 1));

            // Package numbers
            $pkgNumber = $pkg['packageNumber'] ?? $pkg['pieceId'] ?? null;
            if (is_array($pkgNumber)) {
                $pkgNumber = implode(', ', array_filter(array_map('trim', $pkgNumber), static fn($v) => $v !== ''));
            } elseif ($pkgNumber !== null) {
                $pkgNumber = trim((string)$pkgNumber);
            }

            if (empty($pkgNumber) && !empty($cleanRootPkgNums)) {
                $slice = array_slice($cleanRootPkgNums, $pkgNumOffset, $qty);
                $pkgNumber = implode(', ', $slice);
            }
            $pkgNumOffset += $qty;
            $pkg['packageNumber'] = (string)($pkgNumber ?? '');

            // Return package numbers
            $retNumber = $pkg['returnShipmentId'] ?? null;
            if (is_array($retNumber)) {
                $retNumber = implode(', ', array_filter(array_map('trim', $retNumber), static fn($v) => $v !== ''));
            } elseif ($retNumber !== null) {
                $retNumber = trim((string)$retNumber);
            }

            if (empty($retNumber) && !empty($cleanRootReturnIds)) {
                $retSlice = array_slice($cleanRootReturnIds, $retIdOffset, $qty);
                $retNumber = implode(', ', $retSlice);
            }
            $retIdOffset += $qty;
            $pkg['returnShipmentId'] = (string)($retNumber ?? '');

            $result[$index] = $pkg;
        }
        return $result;
    }

    /**
     * Normalize customs clearance array flags to string '1'/'0' and assign record_id to dynamic row items.
     *
     * @param array $customs
     *
     * @return array
     */
    private function normalizeCustoms(array $customs): array
    {
        $dto = CustomsData::fromArray($customs);
        $result = $dto->toArray();

        $result['notExceedValue'] = $dto->isNotExceedValue() ? '1' : '0';
        $result['notProhibitedGoods'] = $dto->isNotProhibitedGoods() ? '1' : '0';
        $result['notRestrictedGoods'] = $dto->isNotRestrictedGoods() ? '1' : '0';
        $result['invoiceContent'] = $dto->isInvoiceContent() ? '1' : '0';

        $rawItems = $result['customsItems'] ?? [];
        $normalizedItems = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemFields = ['nameEn', 'namePl', 'quantity', 'weight', 'value', 'tariffCode', 'countryOfOrigin'];
                foreach ($itemFields as $field) {
                    if (!isset($item[$field])) {
                        $item[$field] = '';
                    }
                }
                $item['record_id'] = (string)$index;
                $normalizedItems[$index] = $item;
            }
        }
        $result['customsItems'] = $normalizedItems;

        return $result;
    }

    /**
     * Format status code into human-readable translated label for edit form.
     *
     * @param string $status
     *
     * @return string
     */
    private function formatStatusLabel(string $status): string
    {
        return match ($status) {
            ShipmentDataInterface::STATUS_NEW => (string) __('New'),
            ShipmentDataInterface::STATUS_COURIER_BOOKED => (string) __('Courier Booked'),
            default => (string) __($status),
        };
    }
}
