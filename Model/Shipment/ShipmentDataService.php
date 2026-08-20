<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Exception;
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
     * @param LoggerInterface|null $logger
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
        private readonly ?LoggerInterface $logger = null
    ) {
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
        $payload = $entity->getShipmentData();

        if (empty($payload)) {
            throw new LocalizedException(__('Shipment data payload is empty for entity #%1.', $entityId));
        }

        try {
            $dataArr = $this->serializer->unserialize($payload);
        } catch (InvalidArgumentException $e) {
            $dataArr = null;
        }

        if (!is_array($dataArr)) {
            throw new LocalizedException(__('Invalid JSON shipment payload for entity #%1.', $entityId));
        }

        return DhlShipmentData::fromArray($dataArr);
    }

    /**
     * Update shipment status, shipment tracking ID, and optional error messages.
     *
     * @param int $entityId
     * @param string $status
     * @param string|null $shipmentId
     * @param string|null $errorMessages
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    public function updateShipmentStatusAndTracking(
        int $entityId,
        string $status,
        ?string $shipmentId = null,
        ?string $errorMessages = null
    ): ShipmentDataInterface {
        $entity = $this->shipmentDataRepository->getById($entityId);
        $entity->setStatus($status);

        if ($shipmentId !== null && trim($shipmentId) !== '') {
            $entity->setShipmentId($shipmentId);
        }

        if ($errorMessages !== null) {
            $entity->setErrorMessages($errorMessages);
        }

        return $this->shipmentDataRepository->save($entity);
    }

    /**
     * Prepare initial DhlShipmentData object for given order ID.
     *
     * @param int $orderId
     * @param int|null $presetSenderId
     * @param int|null $presetShipmentId
     *
     * @return DhlShipmentData
     * @throws LocalizedException
     */
    public function prepareShipmentData(
        int $orderId,
        ?int $presetSenderId = null,
        ?int $presetShipmentId = null
    ): DhlShipmentData {
        $order = $this->orderRepository->get($orderId);

        // 1. Sender defaults from DropOffPoint preset
        $senderDefaults = [];
        if ($presetSenderId) {
            try {
                $senderEntity = $this->dropOffPointRepository->getById($presetSenderId);
                $senderDefaults = $senderEntity->getData();
            } catch (NoSuchEntityException $e) {
                $this->logger?->warning('Specified sender preset not found: ' . $presetSenderId, ['exception' => $e]);
            }
        }
        if (empty($senderDefaults)) {
            $store = method_exists($order, 'getStore') ? $order->getStore() : null;
            $websiteId = $store ? (int)($store->getWebsiteId() ?? 0) : 0;
            $defaultSender = $this->dropOffPointRepository->getDefaultByWebsiteId($websiteId);
            if ($defaultSender) {
                $senderDefaults = $defaultSender->getData();
            }
        }

        // 2. Shipment defaults from DeliveryService preset
        $shipmentDefaults = [];
        if ($presetShipmentId) {
            try {
                $serviceEntity = $this->deliveryServiceRepository->getById($presetShipmentId);
                $shipmentDefaults = $serviceEntity->getData();
                if (!empty($shipmentDefaults['additional_services'])) {
                    $servicesArr = $shipmentDefaults['additional_services'];
                    if (is_string($servicesArr)) {
                        try {
                            $servicesArr = $this->serializer->unserialize($servicesArr);
                        } catch (InvalidArgumentException $e) {
                            $servicesArr = [];
                        }
                    }
                    if (is_array($servicesArr)) {
                        $shipmentDefaults['specialServices'] = $servicesArr;
                    }
                }
            } catch (NoSuchEntityException $e) {
                $this->logger?->warning(
                    'Specified shipment preset not found: ' . $presetShipmentId,
                    ['exception' => $e]
                );
            }
        }
        if (empty($shipmentDefaults)) {
            $shippingMethod = method_exists($order, 'getShippingMethod')
                ? (string)($order->getShippingMethod() ?? '')
                : '';
            if (!empty($shippingMethod)) {
                $store = method_exists($order, 'getStore') ? $order->getStore() : null;
                $websiteId = $store ? (int)($store->getWebsiteId() ?? 0) : 0;
                $matchedService = $this->deliveryServiceRepository->getByWebsiteAndMethod($websiteId, $shippingMethod);
                if ($matchedService) {
                    $shipmentDefaults = $matchedService->getData();
                }
            }
        }

        return $this->mapper->map($order, $senderDefaults, $shipmentDefaults);
    }

    /**
     * Save DhlShipmentData object into database with custom status.
     *
     * @param DhlShipmentData $shipmentData
     * @param string $status
     * @param string|null $errorMessages
     * @param string|null $presetSenderId
     * @param string|null $presetShipmentId
     * @param string|null $shipmentId
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function saveShipmentDataWithStatus(
        DhlShipmentData $shipmentData,
        string $status = ShipmentStatus::DISPATCHED,
        ?string $errorMessages = null,
        ?string $presetSenderId = null,
        ?string $presetShipmentId = null,
        ?string $shipmentId = null
    ): ShipmentDataInterface {
        $this->validator->validate($shipmentData);

        $orderId = $shipmentData->getOrderId();
        $entity = $this->shipmentDataFactory->create();

        $payloadArr = $shipmentData->toArray();
        if ($shipmentId !== null && trim($shipmentId) !== '') {
            $payloadArr['shipmentId'] = (string)$shipmentId;
        }

        $entity->setOrderId($orderId);
        $entity->setStatus($status);
        if ($shipmentId !== null && trim($shipmentId) !== '') {
            $entity->setShipmentId($shipmentId);
        }
        $entity->setPresetSenderId($presetSenderId);
        $entity->setPresetShipmentId($presetShipmentId);
        $entity->setShipmentData($this->serializer->serialize($payloadArr));
        $entity->setErrorMessages($errorMessages);

        return $this->shipmentDataRepository->save($entity);
    }

    /**
     * Save DhlShipmentData object into database persistence.
     *
     * @param DhlShipmentData $shipmentData
     * @param string|null $presetSenderId
     * @param string|null $presetShipmentId
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    public function saveShipmentData(
        DhlShipmentData $shipmentData,
        ?string $presetSenderId = null,
        ?string $presetShipmentId = null
    ): ShipmentDataInterface {
        return $this->saveShipmentDataWithStatus(
            $shipmentData,
            ShipmentStatus::DISPATCHED,
            null,
            $presetSenderId,
            $presetShipmentId
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
}
