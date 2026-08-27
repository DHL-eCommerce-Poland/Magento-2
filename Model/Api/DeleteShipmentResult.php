<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class DeleteShipmentResult
{
    /**
     * @param bool $success
     * @param string|null $shipmentId
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $shipmentId = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Whether the shipment deletion succeeded.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the DHL tracking number / shipment ID.
     *
     * @return string|null
     */
    public function getShipmentId(): ?string
    {
        return $this->shipmentId;
    }

    /**
     * Get the error code if deletion failed.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get the error message if deletion failed.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
