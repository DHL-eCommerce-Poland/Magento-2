<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api\Data;

interface ShipmentDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const ORDER_ID = 'order_id';
    public const MAGENTO_SHIPMENT_ID = 'magento_shipment_id';
    public const SHIPMENT_ID = 'shipment_id';
    public const RETURN_SHIPMENT_ID = 'return_shipment_id';
    public const DISPATCH_NUMBER = 'dispatch_number';
    public const STATUS = 'status';
    public const PRESET_SENDER_ID = 'preset_sender_id';
    public const PRESET_SHIPMENT_ID = 'preset_shipment_id';
    public const SHIPMENT_DATA = 'shipment_data';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const DEFAULT_STATUS = 'Nowa';

    /**
     * Get entity ID.
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Set entity ID.
     *
     * @param mixed $entityId
     *
     * @return $this
     */
    public function setEntityId(mixed $entityId): static;

    /**
     * Get Magento order ID.
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set Magento order ID.
     *
     * @param int|null $orderId
     *
     * @return $this
     */
    public function setOrderId(?int $orderId): static;

    /**
     * Get Magento sales shipment ID.
     *
     * @return int|null
     */
    public function getMagentoShipmentId(): ?int;

    /**
     * Set Magento sales shipment ID.
     *
     * @param int|null $magentoShipmentId
     *
     * @return $this
     */
    public function setMagentoShipmentId(?int $magentoShipmentId): static;

    /**
     * Get DHL shipment ID.
     *
     * @return string|null
     */
    public function getShipmentId(): ?string;

    /**
     * Set DHL shipment ID.
     *
     * @param string|null $shipmentId
     *
     * @return $this
     */
    public function setShipmentId(?string $shipmentId): static;

    /**
     * Get DHL return shipment ID / waybill.
     *
     * @return string|null
     */
    public function getReturnShipmentId(): ?string;

    /**
     * Set DHL return shipment ID.
     *
     * @param string|null $returnShipmentId
     *
     * @return $this
     */
    public function setReturnShipmentId(?string $returnShipmentId): static;

    /**
     * Get DHL courier order / dispatch number.
     *
     * @return string|null
     */
    public function getDispatchNumber(): ?string;

    /**
     * Set DHL courier order / dispatch number.
     *
     * @param string|null $dispatchNumber
     *
     * @return $this
     */
    public function setDispatchNumber(?string $dispatchNumber): static;

    /**
     * Get shipment status.
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set shipment status.
     *
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status): static;

    /**
     * Get selected sender preset ID.
     *
     * @return string|null
     */
    public function getPresetSenderId(): ?string;

    /**
     * Set selected sender preset ID.
     *
     * @param string|null $presetSenderId
     *
     * @return $this
     */
    public function setPresetSenderId(?string $presetSenderId): static;

    /**
     * Get selected shipment preset ID.
     *
     * @return string|null
     */
    public function getPresetShipmentId(): ?string;

    /**
     * Set selected shipment preset ID.
     *
     * @param string|null $presetShipmentId
     *
     * @return $this
     */
    public function setPresetShipmentId(?string $presetShipmentId): static;

    /**
     * Get shipment JSON payload.
     *
     * @return string|null
     */
    public function getShipmentData(): ?string;

    /**
     * Set shipment JSON payload.
     *
     * @param string|null $shipmentData
     *
     * @return $this
     */
    public function setShipmentData(?string $shipmentData): static;

    /**
     * Get creation timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set creation timestamp.
     *
     * @param string|null $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): static;

    /**
     * Get update timestamp.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set update timestamp.
     *
     * @param string|null $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): static;
}
