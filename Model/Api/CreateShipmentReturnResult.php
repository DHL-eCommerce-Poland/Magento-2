<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class CreateShipmentReturnResult
{
    /**
     * @param bool $success
     * @param bool $timeout
     * @param string|null $shipmentId
     * @param string|null $waybill
     * @param string|null $labelData
     * @param string|null $labelMimeType
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly bool $timeout = false,
        private readonly ?string $shipmentId = null,
        private readonly ?string $waybill = null,
        private readonly ?string $labelData = null,
        private readonly ?string $labelMimeType = null,
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
     * Get the DHL return tracking number / shipment ID.
     *
     * @return string|null
     */
    public function getShipmentId(): ?string
    {
        return $this->shipmentId;
    }

    /**
     * Get the waybill number.
     *
     * @return string|null
     */
    public function getWaybill(): ?string
    {
        return $this->waybill;
    }

    /**
     * Get binary return label content if provided.
     *
     * @return string|null
     */
    public function getLabelData(): ?string
    {
        return $this->labelData;
    }

    /**
     * Get return label mime type (e.g. application/pdf, text/plain).
     *
     * @return string|null
     */
    public function getLabelMimeType(): ?string
    {
        return $this->labelMimeType;
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
