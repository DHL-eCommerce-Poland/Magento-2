<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api\Data;

interface ShipmentDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const ORDER_ID = 'order_id';
    public const SHIPMENT_ID = 'shipment_id';
    public const STATUS = 'status';
    public const PRESET_SENDER_ID = 'preset_sender_id';
    public const PRESET_SHIPMENT_ID = 'preset_shipment_id';
    public const SHIPMENT_DATA = 'shipment_data';
    public const ERROR_MESSAGES = 'error_messages';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

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
     * Get DHL waybill shipment ID.
     *
     * @return string|null
     */
    public function getShipmentId(): ?string;

    /**
     * Set DHL waybill shipment ID.
     *
     * @param string|null $shipmentId
     *
     * @return $this
     */
    public function setShipmentId(?string $shipmentId): static;

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
     * Get error messages JSON string.
     *
     * @return string|null
     */
    public function getErrorMessages(): ?string;

    /**
     * Set error messages JSON string.
     *
     * @param string|null $errorMessages
     *
     * @return $this
     */
    public function setErrorMessages(?string $errorMessages): static;

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
