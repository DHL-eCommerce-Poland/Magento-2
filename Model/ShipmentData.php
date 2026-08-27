<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class ShipmentData extends AbstractModel implements ShipmentDataInterface
{
    /**
     * Initialize resource model.
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(\Ideo\Dhl\Model\ResourceModel\ShipmentData::class);
    }

    /**
     * Get entity ID.
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) !== null ? (int)$this->getData(self::ENTITY_ID) : null;
    }

    /**
     * Set entity ID.
     *
     * @param mixed $entityId
     *
     * @return $this
     */
    public function setEntityId(mixed $entityId): static
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * Get order ID.
     *
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->getData(self::ORDER_ID) !== null ? (int)$this->getData(self::ORDER_ID) : null;
    }

    /**
     * Set order ID.
     *
     * @param int|null $orderId
     *
     * @return $this
     */
    public function setOrderId(?int $orderId): static
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * Get DHL shipment ID.
     *
     * @return string|null
     */
    public function getShipmentId(): ?string
    {
        $val = $this->getData(self::SHIPMENT_ID);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set DHL shipment ID.
     *
     * @param string|null $shipmentId
     *
     * @return $this
     */
    public function setShipmentId(?string $shipmentId): static
    {
        return $this->setData(self::SHIPMENT_ID, $shipmentId);
    }

    /**
     * Get shipment status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?? self::DEFAULT_STATUS);
    }

    /**
     * Set shipment status.
     *
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status): static
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * Get preset sender ID.
     *
     * @return string|null
     */
    public function getPresetSenderId(): ?string
    {
        $val = $this->getData(self::PRESET_SENDER_ID);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set preset sender ID.
     *
     * @param string|null $presetSenderId
     *
     * @return $this
     */
    public function setPresetSenderId(?string $presetSenderId): static
    {
        return $this->setData(self::PRESET_SENDER_ID, $presetSenderId);
    }

    /**
     * Get preset shipment ID.
     *
     * @return string|null
     */
    public function getPresetShipmentId(): ?string
    {
        $val = $this->getData(self::PRESET_SHIPMENT_ID);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set preset shipment ID.
     *
     * @param string|null $presetShipmentId
     *
     * @return $this
     */
    public function setPresetShipmentId(?string $presetShipmentId): static
    {
        return $this->setData(self::PRESET_SHIPMENT_ID, $presetShipmentId);
    }

    /**
     * Get shipment payload JSON.
     *
     * @return string|null
     */
    public function getShipmentData(): ?string
    {
        return $this->getData(self::SHIPMENT_DATA) !== null ? (string)$this->getData(self::SHIPMENT_DATA) : null;
    }

    /**
     * Set shipment payload JSON.
     *
     * @param string|null $shipmentData
     *
     * @return $this
     */
    public function setShipmentData(?string $shipmentData): static
    {
        return $this->setData(self::SHIPMENT_DATA, $shipmentData);
    }

    /**
     * Get error messages JSON string.
     *
     * @return string|null
     */
    public function getErrorMessages(): ?string
    {
        return $this->getData(self::ERROR_MESSAGES) !== null ? (string)$this->getData(self::ERROR_MESSAGES) : null;
    }

    /**
     * Set error messages JSON string.
     *
     * @param string|null $errorMessages
     *
     * @return $this
     */
    public function setErrorMessages(?string $errorMessages): static
    {
        return $this->setData(self::ERROR_MESSAGES, $errorMessages);
    }

    /**
     * Get creation date.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT) !== null ? (string)$this->getData(self::CREATED_AT) : null;
    }

    /**
     * Set creation date.
     *
     * @param string|null $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): static
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * Get update date.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT) !== null ? (string)$this->getData(self::UPDATED_AT) : null;
    }

    /**
     * Set update date.
     *
     * @param string|null $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): static
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
