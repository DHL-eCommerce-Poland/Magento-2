<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class CreateShipmentsResult
{
    /**
     * @param bool $success
     * @param bool $timeout
     * @param string|null $shipmentId
     * @param string|null $errorCode
     * @param string|null $errorMessage
     * @param string|null $orderStatus
     */
    public function __construct(
        private readonly bool $success,
        private readonly bool $timeout = false,
        private readonly ?string $shipmentId = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly ?string $orderStatus = null
    ) {
    }

    /**
     * Whether the shipment creation succeeded.
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
     * Get the DHL tracking number / shipment ID.
     *
     * @return string|null
     */
    public function getShipmentId(): ?string
    {
        return $this->shipmentId;
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

    /**
     * Get the DHL order status returned by API (e.g. "Nowa").
     *
     * @return string|null
     */
    public function getOrderStatus(): ?string
    {
        return $this->orderStatus;
    }
}
