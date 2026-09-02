<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class CreateShipmentsResult
{
    /**
     * @param bool $success
     * @param bool $timeout
     * @param string|null $shipmentId
     * @param string|null $orderStatus
     * @param array $packageNumbers
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly bool $timeout = false,
        private readonly ?string $shipmentId = null,
        private readonly ?string $orderStatus = null,
        private readonly array $packageNumbers = [],
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
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
     * Get the DHL order status returned by API (e.g. "Nowa").
     *
     * @return string|null
     */
    public function getOrderStatus(): ?string
    {
        return $this->orderStatus;
    }

    /**
     * Get the DHL package numbers / piece IDs returned by API.
     *
     * @return string[]
     */
    public function getPackageNumbers(): array
    {
        return $this->packageNumbers;
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
