<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class CourierBookingResult
{
    /**
     * @param bool $success
     * @param string|null $courierOrderId
     * @param string|null $errorCode
     * @param string|null $errorMessage
     * @param string|null $orderStatus Status returned by DHL API or formatted with DHL Order ID
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $courierOrderId = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly ?string $orderStatus = null
    ) {
    }

    /**
     * Check if courier booking was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get courier order ID / confirmation number returned by DHL WebAPI2.
     *
     * @return string|null
     */
    public function getCourierOrderId(): ?string
    {
        return $this->courierOrderId;
    }

    /**
     * Get error code.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get error message.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get order status returned by DHL API or formatted with DHL Order ID.
     *
     * @return string|null
     */
    public function getOrderStatus(): ?string
    {
        return $this->orderStatus;
    }
}
