<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\ShipmentDataFactory;
use InvalidArgumentException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ShipmentDataService
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderToShipmentDataMapper $mapper
     * @param ShipmentDataValidator $validator
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ShipmentDataFactory $shipmentDataFactory
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderToShipmentDataMapper $mapper,
        private readonly ShipmentDataValidator $validator,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository,
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ShipmentDataFactory $shipmentDataFactory,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Load persistent shipment data DB entity by entity ID.
     *
     * @param int $entityId
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    public function getShipmentEntity(int $entityId): ShipmentDataInterface
    {
        return $this->shipmentDataRepository->getById($entityId);
    }

    /**
     * Load DhlShipmentData DTO for given entity ID.
     *
     * @param int $entityId
     *
     * @return DhlShipmentData
     * @throws LocalizedException
     */
    public function loadShipmentData(int $entityId): DhlShipmentData
    {
        $entity = $this->shipmentDataRepository->getById($entityId);
        return $this->loadShipmentDataFromEntity($entity);
    }

    /**
     * Load DhlShipmentData DTO from a ShipmentDataInterface entity.
     *
     * @param ShipmentDataInterface $entity
     *
     * @return DhlShipmentData
     * @throws LocalizedException
     */
    public function loadShipmentDataFromEntity(ShipmentDataInterface $entity): DhlShipmentData
    {
        $payload = $entity->getShipmentData();

        if (empty($payload)) {
            throw new LocalizedException(__('Shipment data payload is empty for entity #%1.', $entity->getEntityId()));
        }

        try {
            $dataArr = $this->serializer->unserialize($payload);
        } catch (InvalidArgumentException $e) {
            $dataArr = null;
        }

        if (!is_array($dataArr)) {
            throw new LocalizedException(__('Invalid JSON shipment payload for entity #%1.', $entity->getEntityId()));
        }

        return DhlShipmentData::fromArray($dataArr);
    }

    /**
     * Prepare initial DhlShipmentData object for given order ID.
     *
     * @param int $orderId
     * @param int|null $dropOffPointTemplateId
     * @param int|null $deliveryServiceTemplateId
     * @return DhlShipmentData
     * @throws LocalizedException
     */
    public function prepareShipmentData(
        int $orderId,
        ?int $dropOffPointTemplateId = null,
        ?int $deliveryServiceTemplateId = null
    ): DhlShipmentData {
        $order = $this->orderRepository->get($orderId);

        // 1. Sender defaults from DropOffPoint preset
        $senderDefaults = [];
        if ($dropOffPointTemplateId) {
            try {
                $senderEntity = $this->dropOffPointRepository->getById($dropOffPointTemplateId);
                $senderDefaults = $senderEntity->getData();
            } catch (NoSuchEntityException $e) {
                $this->logger->warning(
                    'Specified sender preset not found: ' . $dropOffPointTemplateId,
                    ['exception' => $e]
                );
            }
        }
        if (empty($senderDefaults)) {
            $defaultSender = $this->dropOffPointRepository->getDefault();
            if ($defaultSender) {
                $senderDefaults = $defaultSender->getData();
            }
        }

        // 2. Shipment defaults from DeliveryService preset
        $shipmentDefaults = [];
        if ($deliveryServiceTemplateId) {
            try {
                $serviceEntity = $this->deliveryServiceRepository->getById($deliveryServiceTemplateId);
                $shipmentDefaults = $serviceEntity->getData();
                $servicesArr = $this->decodeAdditionalServices($shipmentDefaults['additional_services'] ?? null);
                if ($servicesArr !== null) {
                    $shipmentDefaults['specialServices'] = $servicesArr;
                }
                $shipmentDefaults['packages'] = $serviceEntity->getPackages();
            } catch (NoSuchEntityException $e) {
                $this->logger->warning(
                    'Specified shipment preset not found: ' . $deliveryServiceTemplateId,
                    ['exception' => $e]
                );
            }
        }
        if (empty($shipmentDefaults)) {
            $shippingMethod = method_exists($order, 'getShippingMethod')
                ? (string)($order->getShippingMethod() ?? '')
                : '';
            if (!empty($shippingMethod)) {
                $matchedService = $this->deliveryServiceRepository->getByMethod($shippingMethod);
                if ($matchedService) {
                    $shipmentDefaults = $matchedService->getData();
                    $servicesArr = $this->decodeAdditionalServices($shipmentDefaults['additional_services'] ?? null);
                    if ($servicesArr !== null) {
                        $shipmentDefaults['specialServices'] = $servicesArr;
                    }
                    $shipmentDefaults['packages'] = $matchedService->getPackages();
                }
            }
        }

        return $this->mapper->map(
            $order,
            $senderDefaults,
            $this->normalizeShipmentDefaults($shipmentDefaults)
        );
    }

    /**
     * Save DhlShipmentData object into database with custom status.
     *
     * @param DhlShipmentData $shipmentData
     * @param string $status
     * @param string|null $shipmentId
     * @param string|null $returnShipmentId
     * @param array|null $packageNumbers
     * @param int|null $magentoShipmentId
     *
     * @return ShipmentDataInterface
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function saveShipmentDataWithStatus(
        DhlShipmentData $shipmentData,
        string $status = ShipmentDataInterface::DEFAULT_STATUS,
        ?string $shipmentId = null,
        ?string $returnShipmentId = null,
        ?array $packageNumbers = null,
        ?int $magentoShipmentId = null
    ): ShipmentDataInterface {
        $this->validator->validate($shipmentData);

        $orderId = $shipmentData->getOrderId();
        $entity = $this->shipmentDataFactory->create();

        $payloadArr = $shipmentData->toArray();
        if ($shipmentId !== null && trim($shipmentId) !== '') {
            $payloadArr['shipmentId'] = (string)$shipmentId;
        }
        if ($packageNumbers !== null && !empty($packageNumbers)) {
            $this->mapPackageNumbers($payloadArr, $packageNumbers);
            $pkgStr = implode(', ', array_values(array_unique(array_filter(array_map('trim', $packageNumbers)))));
            if ($pkgStr !== '') {
                $entity->setPackageNumbers($pkgStr);
            }
        }
        if ($magentoShipmentId !== null) {
            $payloadArr['magentoShipmentId'] = $magentoShipmentId;
        }

        $this->removeInvoiceFileFromPayload($payloadArr);

        $entity->setOrderId($orderId);
        $entity->setMagentoShipmentId($magentoShipmentId);
        $entity->setStatus($status);
        if ($shipmentId !== null && trim($shipmentId) !== '') {
            $entity->setShipmentId($shipmentId);
        }
        if ($returnShipmentId !== null && trim($returnShipmentId) !== '') {
            $entity->setReturnShipmentId($returnShipmentId);
            $retWaybills = array_values(array_filter(
                array_map('trim', explode(',', $returnShipmentId)),
                static fn($v) => $v !== ''
            ));
            if (!empty($retWaybills)) {
                if (isset($payloadArr['shipmentSettings']['packages'])
                    && is_array($payloadArr['shipmentSettings']['packages'])
                ) {
                    $payloadArr['shipmentSettings']['packages'] = $this->distributeNumbersToPackages(
                        $payloadArr['shipmentSettings']['packages'],
                        $retWaybills,
                        'returnShipmentId'
                    );
                    $payloadArr['packages'] = $payloadArr['shipmentSettings']['packages'];
                } elseif (isset($payloadArr['packages']) && is_array($payloadArr['packages'])) {
                    $payloadArr['packages'] = $this->distributeNumbersToPackages(
                        $payloadArr['packages'],
                        $retWaybills,
                        'returnShipmentId'
                    );
                }
            }
        }
        $entity->setShipmentData($this->serializer->serialize($payloadArr));

        return $this->shipmentDataRepository->save($entity);
    }

    /**
     * Update return shipment ID and package-level return tracking details on shipment entity.
     *
     * @param int $entityId
     * @param string $returnShipmentId
     * @param array $returnItems
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    // phpcs:ignore Generic.Metrics.NestingLevel.TooHigh
    public function updateReturnShipmentId(
        int $entityId,
        string $returnShipmentId,
        array $returnItems = []
    ): ShipmentDataInterface {
        $entity = $this->shipmentDataRepository->getById($entityId);
        $returnWaybills = [];
        if (!empty($returnItems) && is_array($returnItems)) {
            foreach ($returnItems as $item) {
                if (is_array($item)) {
                    $wb = (string) ($item['waybill'] ?? ($item['shipmentId'] ?? ''));
                    if (trim($wb) !== '') {
                        $returnWaybills[] = trim($wb);
                    }
                }
            }
        }
        $filteredWaybills = array_values(array_unique(array_filter($returnWaybills)));
        $finalReturnShipmentId = !empty($filteredWaybills)
            ? implode(', ', $filteredWaybills)
            : $returnShipmentId;

        $entity->setReturnShipmentId($finalReturnShipmentId);

        $rawPayload = $entity->getShipmentData();
        if (!empty($rawPayload) && is_string($rawPayload)) {
            try {
                $payloadArr = (array) $this->serializer->unserialize($rawPayload);
                $hasChanged = false;

                $allWaybills = !empty($returnWaybills)
                    ? $returnWaybills
                    : array_values(array_filter(
                        array_map('trim', explode(',', $returnShipmentId)),
                        static fn($v) => $v !== ''
                    ));

                if (!empty($allWaybills)) {
                    if (isset($payloadArr['shipmentSettings']['packages'])
                        && is_array($payloadArr['shipmentSettings']['packages'])
                    ) {
                        $payloadArr['shipmentSettings']['packages'] = $this->distributeNumbersToPackages(
                            $payloadArr['shipmentSettings']['packages'],
                            $allWaybills,
                            'returnShipmentId'
                        );
                        $payloadArr['packages'] = $payloadArr['shipmentSettings']['packages'];
                        $hasChanged = true;
                    } elseif (isset($payloadArr['packages']) && is_array($payloadArr['packages'])) {
                        $payloadArr['packages'] = $this->distributeNumbersToPackages(
                            $payloadArr['packages'],
                            $allWaybills,
                            'returnShipmentId'
                        );
                        $hasChanged = true;
                    }

                    if ($hasChanged) {
                        $entity->setShipmentData($this->serializer->serialize($payloadArr));
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning(sprintf(
                    '[ShipmentDataService] Failed to update package return tracking data for entity #%d: %s',
                    $entityId,
                    $e->getMessage()
                ));
            }
        }

        return $this->shipmentDataRepository->save($entity);
    }

    /**
     * Update existing shipment entity in database with new DhlShipmentData and optional shipmentId.
     *
     * @param int $entityId
     * @param DhlShipmentData $shipmentData
     * @param string $status
     * @param string|null $shipmentId
     * @param array|null $packageNumbers
     * @param int|null $magentoShipmentId
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    public function updateExistingShipmentPayload(
        int $entityId,
        DhlShipmentData $shipmentData,
        string $status = 'created',
        ?string $shipmentId = null,
        ?array $packageNumbers = null,
        ?int $magentoShipmentId = null
    ): ShipmentDataInterface {
        $this->validator->validate($shipmentData);

        $entity = $this->shipmentDataRepository->getById($entityId);
        $payloadArr = $shipmentData->toArray();

        // Reset return shipment ID and package-level return tracking details on recreation
        $entity->setReturnShipmentId(null);
        if (isset($payloadArr['packages']) && is_array($payloadArr['packages'])) {
            foreach ($payloadArr['packages'] as $idx => $pkg) {
                $payloadArr['packages'][$idx]['returnShipmentId'] = null;
            }
        }
        if (isset($payloadArr['shipmentSettings']['packages'])
            && is_array($payloadArr['shipmentSettings']['packages'])
        ) {
            foreach ($payloadArr['shipmentSettings']['packages'] as $idx => $pkg) {
                $payloadArr['shipmentSettings']['packages'][$idx]['returnShipmentId'] = null;
            }
        }

        if ($shipmentId !== null && trim($shipmentId) !== '') {
            $payloadArr['shipmentId'] = (string)$shipmentId;
            $entity->setShipmentId($shipmentId);
        }
        if ($packageNumbers !== null && !empty($packageNumbers)) {
            $this->mapPackageNumbers($payloadArr, $packageNumbers);
            $pkgStr = implode(', ', array_values(array_unique(array_filter(array_map('trim', $packageNumbers)))));
            if ($pkgStr !== '') {
                $entity->setPackageNumbers($pkgStr);
            }
        }
        if ($magentoShipmentId !== null) {
            $payloadArr['magentoShipmentId'] = $magentoShipmentId;
            $entity->setMagentoShipmentId($magentoShipmentId);
        }

        $this->removeInvoiceFileFromPayload($payloadArr);

        $entity->setStatus($status);
        $entity->setShipmentData($this->serializer->serialize($payloadArr));

        return $this->shipmentDataRepository->save($entity);
    }

    /**
     * Clear shipment ID, package tracking numbers, and return shipment ID from existing shipment entity.
     *
     * @param int $entityId
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    public function clearShipmentId(int $entityId): ShipmentDataInterface
    {
        $entity = $this->shipmentDataRepository->getById($entityId);
        $entity->setShipmentId(null);
        $entity->setPackageNumbers(null);
        $entity->setReturnShipmentId(null);

        $rawPayload = $entity->getShipmentData();
        if (!empty($rawPayload) && is_string($rawPayload)) {
            try {
                $payloadArr = (array) $this->serializer->unserialize($rawPayload);
                unset($payloadArr['shipmentId'], $payloadArr['packageNumbers']);
                if (isset($payloadArr['packages']) && is_array($payloadArr['packages'])) {
                    foreach ($payloadArr['packages'] as $idx => &$pkg) {
                        if (is_array($pkg)) {
                            unset($pkg['packageNumber'], $pkg['returnShipmentId']);
                        }
                    }
                    unset($pkg);
                }
                if (isset($payloadArr['shipmentSettings']['packages'])
                    && is_array($payloadArr['shipmentSettings']['packages'])
                ) {
                    foreach ($payloadArr['shipmentSettings']['packages'] as $idx => &$pkg) {
                        if (is_array($pkg)) {
                            unset($pkg['packageNumber'], $pkg['returnShipmentId']);
                        }
                    }
                    unset($pkg);
                }
                $entity->setShipmentData($this->serializer->serialize($payloadArr));
            } catch (Throwable $e) {
                $this->logger->warning(sprintf(
                    '[ShipmentDataService] Failed to clear payload tracking data for entity #%d: %s',
                    $entityId,
                    $e->getMessage()
                ));
            }
        }

        return $this->shipmentDataRepository->save($entity);
    }

    /**
     * Save DhlShipmentData object into database persistence.
     *
     * @param DhlShipmentData $shipmentData
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    public function saveShipmentData(
        DhlShipmentData $shipmentData
    ): ShipmentDataInterface {
        return $this->saveShipmentDataWithStatus(
            $shipmentData,
            ShipmentDataInterface::DEFAULT_STATUS
        );
    }

    /**
     * Get all DHL shipment entities associated with Magento order ID.
     *
     * @param int $orderId
     *
     * @return ShipmentDataInterface[]
     */
    public function getAllShipmentsByOrderId(int $orderId): array
    {
        return $this->shipmentDataRepository->getListByOrderId($orderId);
    }

    /**
     * Check if a DHL shipment entity already exists for given order ID.
     *
     * @param int $orderId
     *
     * @return bool
     */
    public function hasShipmentForOrder(int $orderId): bool
    {
        return $this->shipmentDataRepository->getByOrderId($orderId) !== null;
    }

    /**
     * Decode additional services raw data if needed.
     *
     * @param mixed $servicesRaw
     *
     * @return array|null
     */
    private function decodeAdditionalServices(mixed $servicesRaw): ?array
    {
        if (empty($servicesRaw)) {
            return null;
        }
        if (is_array($servicesRaw)) {
            return $servicesRaw;
        }
        if (is_string($servicesRaw)) {
            try {
                $decoded = $this->serializer->unserialize($servicesRaw);
                return is_array($decoded) ? $decoded : null;
            } catch (InvalidArgumentException) {
                return null;
            }
        }
        return null;
    }

    /**
     * Convert persisted delivery service field names to the mapper's camelCase input format.
     *
     * @param array $defaults
     *
     * @return array
     */
    private function normalizeShipmentDefaults(array $defaults): array
    {
        $keyMap = [
            'label_type' => 'labelType',
            'address_type' => 'addressType',
            'auto_create_return_shipment' => 'autoCreateReturnShipment',
            'return_label_type' => 'returnLabelType',
            'return_qr_code_type' => 'returnQrCodeType',
            'return_label_exp_days' => 'returnLabelExpDays',
            'return_book_courier' => 'returnBookCourier',
        ];

        foreach ($keyMap as $storedKey => $mapperKey) {
            if (array_key_exists($storedKey, $defaults)) {
                $defaults[$mapperKey] = $defaults[$mapperKey] ?? $defaults[$storedKey];
                unset($defaults[$storedKey]);
            }
        }

        return $defaults;
    }

    /**
     * Map package numbers into individual package elements in payload array by quantity.
     *
     * @param array $payloadArr
     * @param array $packageNumbers
     *
     * @return void
     */
    private function mapPackageNumbers(array &$payloadArr, array $packageNumbers): void
    {
        $cleanNumbers = array_values(array_filter(
            array_map('trim', $packageNumbers),
            static fn($val) => $val !== ''
        ));
        $payloadArr['packageNumbers'] = $cleanNumbers;

        if (isset($payloadArr['shipmentSettings']['packages'])
            && is_array($payloadArr['shipmentSettings']['packages'])
        ) {
            $payloadArr['shipmentSettings']['packages'] = $this->distributeNumbersToPackages(
                $payloadArr['shipmentSettings']['packages'],
                $cleanNumbers,
                'packageNumber'
            );
            $payloadArr['packages'] = $payloadArr['shipmentSettings']['packages'];
        } elseif (isset($payloadArr['packages']) && is_array($payloadArr['packages'])) {
            $payloadArr['packages'] = $this->distributeNumbersToPackages(
                $payloadArr['packages'],
                $cleanNumbers,
                'packageNumber'
            );
        }
    }

    /**
     * Distribute tracking numbers sequentially into package rows according to each package's quantity.
     *
     * @param array $packages
     * @param string[] $numbers
     * @param string $targetField
     *
     * @return array
     */
    private function distributeNumbersToPackages(array $packages, array $numbers, string $targetField): array
    {
        $cleanNumbers = array_values(array_filter(
            array_map('trim', $numbers),
            static fn($val) => $val !== ''
        ));

        $offset = 0;
        foreach ($packages as &$pkg) {
            if (!is_array($pkg)) {
                continue;
            }
            $qty = max(1, (int)($pkg['quantity'] ?? 1));
            $assigned = array_slice($cleanNumbers, $offset, $qty);
            $offset += $qty;
            $pkg[$targetField] = implode(', ', $assigned);
        }
        unset($pkg);

        return $packages;
    }

    /**
     * Keep the uploaded VAT invoice in the DHL request only, not in the persisted shipment payload.
     *
     * @param array $payloadArr
     *
     * @return void
     */
    private function removeInvoiceFileFromPayload(array &$payloadArr): void
    {
        if (isset($payloadArr['customs']) && is_array($payloadArr['customs'])) {
            unset($payloadArr['customs']['invoiceFile']);
        }
    }
}
