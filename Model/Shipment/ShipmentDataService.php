<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Exception;
use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\ShipmentData;
use Ideo\Dhl\Model\ShipmentDataFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
        private readonly ?LoggerInterface $logger = null
    ) {
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

        // 1. Existing saved shipment data
        $existing = $this->shipmentDataRepository->getByOrderId($orderId);
        if ($existing && !empty($existing->getShipmentData())) {
            $dataArr = json_decode($existing->getShipmentData(), true);
            if (is_array($dataArr)) {
                return DhlShipmentData::fromArray($dataArr);
            }
        }

        // 2. Sender defaults from DropOffPoint preset
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

        // 3. Shipment defaults from DeliveryService preset
        $shipmentDefaults = [];
        if ($presetShipmentId) {
            try {
                $serviceEntity = $this->deliveryServiceRepository->getById($presetShipmentId);
                $shipmentDefaults = $serviceEntity->getData();
                if (!empty($shipmentDefaults['additional_services'])) {
                    $servicesArr = is_string($shipmentDefaults['additional_services'])
                        ? json_decode($shipmentDefaults['additional_services'], true)
                        : $shipmentDefaults['additional_services'];
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
     * Save DhlShipmentData object into database persistence.
     *
     * @param DhlShipmentData $shipmentData
     * @param string|null $presetSenderId
     * @param string|null $presetShipmentId
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    public function saveShipmentData(
        DhlShipmentData $shipmentData,
        ?string $presetSenderId = null,
        ?string $presetShipmentId = null
    ): ShipmentDataInterface {
        $this->validator->validate($shipmentData);

        $orderId = $shipmentData->getOrderId();
        $entity = null;

        if ($orderId) {
            $entity = $this->shipmentDataRepository->getByOrderId($orderId);
        }

        if (!$entity) {
            /** @var ShipmentDataInterface $entity */
            $entity = $this->shipmentDataFactory->create();
        }

        $entity->setOrderId($orderId);
        $entity->setStatus('pending');
        $entity->setPresetSenderId($presetSenderId);
        $entity->setPresetShipmentId($presetShipmentId);
        $entity->setShipmentData(json_encode($shipmentData->toArray()));
        $entity->setErrorMessages(null);

        return $this->shipmentDataRepository->save($entity);
    }

    /**
     * Prepare and save mass shipment data entities for given array of order IDs.
     *
     * @param int[] $orderIds
     * @return array
     */
    public function prepareAndSaveMassShipmentData(array $orderIds): array
    {
        $created = 0;
        $errors = 0;

        foreach ($orderIds as $orderId) {
            try {
                $shipmentData = $this->prepareShipmentData((int)$orderId);
                $this->saveShipmentData($shipmentData);
                $created++;
            } catch (Exception $e) {
                $this->logger?->error(
                    'Error preparing mass shipment for order ' . $orderId . ': ' . $e->getMessage(),
                    ['exception' => $e]
                );
                $errors++;
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
        ];
    }
}
