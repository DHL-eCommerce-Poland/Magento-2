<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
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
     * @param LoggerInterface|null $logger
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
        private readonly ?LoggerInterface $logger = null,
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
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $item) {
            $data = $item->getData();
            $rawPayload = $item->getShipmentData();

            if (!empty($rawPayload) && is_string($rawPayload)) {
                try {
                    $payload = $this->serializer->unserialize($rawPayload);
                    if (is_array($payload)) {
                        if (isset($payload['sender']) && is_array($payload['sender'])) {
                            $data['sender'] = $payload['sender'];
                        }
                        if (isset($data['sender']) && is_array($data['sender'])) {
                            $presetId = $item->getData('preset_sender_id');
                            if (!isset($data['sender']['presetSenderId']) && !empty($presetId) && is_scalar($presetId)) {
                                $data['sender']['presetSenderId'] = (string)$presetId;
                            }
                        }
                        if (isset($payload['recipient']) && is_array($payload['recipient'])) {
                            $data['recipient'] = $payload['recipient'];
                        }
                        if (isset($payload['shipmentSettings']) && is_array($payload['shipmentSettings'])) {
                            $shipmentId = (string)($item->getShipmentId() ?? ($payload['shipmentId'] ?? ''));
                            $status = (string)($item->getStatus() ?? '');
                            $createdAt = (string)($item->getCreatedAt() ?? '');
                            $dispatchNumber = (string)($item->getDispatchNumber() ?? '');
                            $returnShipmentId = (string)($item->getReturnShipmentId() ?? '');
                            $data['shipmentSettings'] = $this->normalizeShipmentSettings(
                                $payload['shipmentSettings'],
                                $shipmentId,
                                $status,
                                $createdAt,
                                $payload['sender'] ?? [],
                                $dispatchNumber,
                                $returnShipmentId
                            );
                            if (isset($payload['shipmentSettings']['packages']) && is_array($payload['shipmentSettings']['packages'])) {
                                $data['packages'] = $this->normalizePackages($payload['shipmentSettings']['packages']);
                                $data['packages_fieldset'] = ['packages' => $data['packages']];
                            }
                        }
                        if (isset($payload['specialServices']) && is_array($payload['specialServices'])) {
                            $data['specialServices'] = $this->normalizeSpecialServices($payload['specialServices']);
                            $data['special_services'] = ['specialServices' => $data['specialServices']];
                        }
                        if (!isset($data['packages']) && isset($payload['packages']) && is_array($payload['packages'])) {
                            $data['packages'] = $this->normalizePackages($payload['packages']);
                            $data['packages_fieldset'] = ['packages' => $data['packages']];
                        }
                    }
                } catch (Throwable $e) {
                    $this->logger?->warning(sprintf(
                        '[DataProvider] Failed to unserialize shipment_data payload for shipment entity #%s: %s',
                        $item->getId(),
                        $e->getMessage()
                    ));
                }
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
        $isEvening = !empty($ss['eveningDelivery'])
            || ($dto->getDeliveryHours() !== null && (string)$dto->getDeliveryHours() === '1722');

        return [
            'insurance' => $dto->isInsurance() ? '1' : '0',
            'insuranceValue' => $dto->isInsurance() ? $dto->getInsuranceValue() : null,
            'cod' => $dto->isCod() ? '1' : '0',
            'codAmount' => $dto->isCod() ? $dto->getCodAmount() : null,
            'eveningDelivery' => $isEvening ? '1' : '0',
            'saturdayDelivery' => $dto->isSaturdayDelivery() ? '1' : '0',
            'saturdayDispatch' => $dto->isSaturdayDispatch() ? '1' : '0',
            'pdi' => $dto->isPdi() ? '1' : '0',
            'rod' => $dto->isRod() ? '1' : '0',
            'rodDocumentText' => $dto->isRod() ? $dto->getRodDocumentText() : '',
            'pod' => $dto->isPod() ? '1' : '0',
            'agever' => $dto->isAgever() ? '1' : '0',
            'odb' => $dto->isOdb() ? '1' : '0',
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
     *
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
        if (empty($settings['shipmentId']) && !empty($shipmentId)) {
            $settings['shipmentId'] = $shipmentId;
        }
        if (!empty($status)) {
            $settings['status'] = $status;
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
        $today = $this->timezone->scopeDate()->format('Y-m-d');
        $rawDate = $settings['shipmentDate'] ?? null;
        $finalDate = (empty($rawDate) || $rawDate < $today) ? $today : $rawDate;
        $settings['shipmentDate'] = $finalDate;

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

        return $settings;
    }

    /**
     * Normalize packages array flags to string '1'/'0' and add record_id.
     *
     * @param array $packages
     *
     * @return array
     */
    private function normalizePackages(array $packages): array
    {
        $result = [];
        foreach ($packages as $index => $pkg) {
            if (is_array($pkg)) {
                $pkg['record_id'] = (string)$index;
                $pkg['isNonStandard'] = !empty($pkg['isNonStandard']) ? '1' : '0';
                $pkg['hasEuroReturn'] = !empty($pkg['hasEuroReturn']) ? '1' : '0';
                $result[$index] = $pkg;
            }
        }
        return $result;
    }
}
