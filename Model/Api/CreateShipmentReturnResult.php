<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class CreateShipmentReturnResult
{
    /**
     * @param bool $success
     * @param bool $timeout
     * @param array $items
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly bool $timeout = false,
        private readonly array $items = [],
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Whether the return shipment creation succeeded.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Whether a network/SOAP timeout occurred.
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return $this->timeout;
    }

    /**
     * Get all return items created by DHL.
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get list of all return shipment tracking numbers / waybills.
     *
     * @return string[]
     */
    public function getShipmentIds(): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            if (!empty($item['shipmentId'])) {
                $ids[] = (string) $item['shipmentId'];
            }
        }
        return $ids;
    }

    /**
     * Get primary (first) return shipment ID.
     *
     * @return string|null
     */
    public function getPrimaryShipmentId(): ?string
    {
        $ids = $this->getShipmentIds();
        return $ids[0] ?? null;
    }

    /**
     * Get shipment ID (comma-separated string if multiple items).
     *
     * @return string|null
     */
    public function getShipmentId(): ?string
    {
        $ids = $this->getShipmentIds();
        return !empty($ids) ? implode(', ', $ids) : null;
    }

    /**
     * Get waybill number (comma-separated string if multiple items).
     *
     * @return string|null
     */
    public function getWaybill(): ?string
    {
        $waybills = [];
        foreach ($this->items as $item) {
            $waybill = $item['waybill'] ?? $item['shipmentId'] ?? null;
            if (!empty($waybill)) {
                $waybills[] = (string) $waybill;
            }
        }
        return !empty($waybills) ? implode(', ', $waybills) : null;
    }

    /**
     * Get primary or combined label binary content.
     *
     * @return string|null
     */
    public function getLabelData(): ?string
    {
        $labels = [];
        foreach ($this->items as $item) {
            if (!empty($item['labelData'])) {
                $labels[] = $item['labelData'];
            }
        }
        return !empty($labels) ? implode('', $labels) : null;
    }

    /**
     * Get primary return label mime type.
     *
     * @return string|null
     */
    public function getLabelMimeType(): ?string
    {
        foreach ($this->items as $item) {
            if (!empty($item['labelMimeType'])) {
                return (string) $item['labelMimeType'];
            }
        }
        return null;
    }

    /**
     * Get the error code if creation failed.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get the error message if creation failed.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
