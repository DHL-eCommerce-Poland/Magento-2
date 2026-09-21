<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;

class DhlShipmentData
{
    /**
     * @param int|null $orderId
     * @param SenderData|null $sender
     * @param RecipientData|null $recipient
     * @param SpecialServicesData|null $specialServices
     * @param ShipmentSettingsData|null $shipmentSettings
     * @param array $itemsQty
     */
    public function __construct(
        private readonly ?int $orderId = null,
        private ?SenderData $sender = null,
        private ?RecipientData $recipient = null,
        private ?SpecialServicesData $specialServices = null,
        private ?ShipmentSettingsData $shipmentSettings = null,
        private array $itemsQty = []
    ) {
        $this->sender = $sender ?? new SenderData();
        $this->recipient = $recipient ?? new RecipientData();
        $this->specialServices = $specialServices ?? new SpecialServicesData();
        $this->shipmentSettings = $shipmentSettings ?? new ShipmentSettingsData();
    }

    /**
     * Get Magento order ID.
     *
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    /**
     * Get items quantities per order item ID.
     *
     * @return array
     */
    public function getItemsQty(): array
    {
        return $this->itemsQty;
    }

    /**
     * Set items quantities per order item ID.
     *
     * @param array $itemsQty
     *
     * @return self
     */
    public function setItemsQty(array $itemsQty): self
    {
        $this->itemsQty = $itemsQty;
        return $this;
    }

    /**
     * Get sender data.
     *
     * @return SenderData
     */
    public function getSender(): SenderData
    {
        return $this->sender;
    }

    /**
     * Get recipient data.
     *
     * @return RecipientData
     */
    public function getRecipient(): RecipientData
    {
        return $this->recipient;
    }

    /**
     * Get special services data.
     *
     * @return SpecialServicesData
     */
    public function getSpecialServices(): SpecialServicesData
    {
        return $this->specialServices;
    }

    /**
     * Get shipment settings data.
     *
     * @return ShipmentSettingsData
     */
    public function getShipmentSettings(): ShipmentSettingsData
    {
        return $this->shipmentSettings;
    }

    /**
     * Convert DTO structure to array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'itemsQty' => $this->itemsQty,
            'sender' => $this->sender->toArray(),
            'recipient' => $this->recipient->toArray(),
            'specialServices' => $this->specialServices->toArray(),
            'shipmentSettings' => $this->shipmentSettings->toArray(),
        ];
    }

    /**
     * Create DTO structure from array representation.
     *
     * @param array $data
     *
     * @return self
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function fromArray(array $data): self
    {
        $shipmentSettings = $data['shipmentSettings'] ?? [];
        if (!isset($shipmentSettings['packages'])) {
            if (isset($data['packages']) && is_array($data['packages'])) {
                $shipmentSettings['packages'] = $data['packages'];
            } elseif (isset($data['packages_fieldset']['packages'])
                && is_array($data['packages_fieldset']['packages'])
            ) {
                $shipmentSettings['packages'] = $data['packages_fieldset']['packages'];
            }
        }

        return new self(
            isset($data['orderId']) ? (int)$data['orderId'] : null,
            SenderData::fromArray($data['sender'] ?? []),
            RecipientData::fromArray($data['recipient'] ?? []),
            SpecialServicesData::fromArray($data['specialServices'] ?? []),
            ShipmentSettingsData::fromArray($shipmentSettings),
            is_array($data['itemsQty'] ?? null) ? $data['itemsQty'] : []
        );
    }
}
