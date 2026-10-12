<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Model\Shipment\Data\CustomsData;
use DhlPoland\Dhl\Model\Shipment\Data\RecipientData;
use DhlPoland\Dhl\Model\Shipment\Data\SenderData;
use DhlPoland\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use DhlPoland\Dhl\Model\Shipment\Data\SpecialServicesData;

class DhlShipmentData
{
    /**
     * @param int|null $orderId
     * @param SenderData|null $sender
     * @param RecipientData|null $recipient
     * @param SpecialServicesData|null $specialServices
     * @param ShipmentSettingsData|null $shipmentSettings
     * @param array $itemsQty
     * @param CustomsData|null $customs
     * @param float|null $orderGrandTotal
     */
    public function __construct(
        private readonly ?int $orderId = null,
        private ?SenderData $sender = null,
        private ?RecipientData $recipient = null,
        private ?SpecialServicesData $specialServices = null,
        private ?ShipmentSettingsData $shipmentSettings = null,
        private array $itemsQty = [],
        private ?CustomsData $customs = null,
        private readonly ?float $orderGrandTotal = null
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
     * Get customs clearance data.
     *
     * @return CustomsData|null
     */
    public function getCustoms(): ?CustomsData
    {
        return $this->customs;
    }

    /**
     * Set customs clearance data.
     *
     * @param CustomsData|null $customs
     *
     * @return self
     */
    public function setCustoms(?CustomsData $customs): self
    {
        $this->customs = $customs;
        return $this;
    }

    /**
     * Get order grand total amount.
     *
     * @return float|null
     */
    public function getOrderGrandTotal(): ?float
    {
        return $this->orderGrandTotal;
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
            'orderGrandTotal' => $this->orderGrandTotal,
            'itemsQty' => $this->itemsQty,
            'sender' => $this->sender->toArray(),
            'recipient' => $this->recipient->toArray(),
            'specialServices' => $this->specialServices->toArray(),
            'shipmentSettings' => $this->shipmentSettings->toArray(),
            'customs' => $this->customs?->toArray(),
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
        $packages = array_filter([
            $data['packages'] ?? null,
            $data['packages_fieldset']['packages'] ?? null,
            $data['shipmentSettings']['packages'] ?? null,
        ]);
        if ($packages) {
            $shipmentSettings['packages'] = reset($packages);
        }

        $customs = null;
        if (isset($data['customs']) && is_array($data['customs'])) {
            $customs = CustomsData::fromArray($data['customs']);
        }

        $specialServicesRaw = $data['specialServices']
            ?? ($data['special_services']['specialServices'] ?? ($data['special_services'] ?? []));
        if (!is_array($specialServicesRaw)) {
            $specialServicesRaw = [];
        }

        return new self(
            isset($data['orderId']) ? (int)$data['orderId'] : null,
            SenderData::fromArray($data['sender'] ?? []),
            RecipientData::fromArray($data['recipient'] ?? []),
            SpecialServicesData::fromArray($specialServicesRaw),
            ShipmentSettingsData::fromArray($shipmentSettings),
            is_array($data['itemsQty'] ?? null) ? $data['itemsQty'] : [],
            $customs,
            is_numeric($data['orderGrandTotal'] ?? null) ? (float)$data['orderGrandTotal'] : null
        );
    }
}
