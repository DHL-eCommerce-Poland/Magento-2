<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class CourierBookingResult
{
    /**
     * @param bool $success
     * @param string[] $courierOrderIds
     * @param string|null $errorCode
     * @param string|null $errorMessage
     * @param string|null $orderStatus Status returned by DHL API or formatted with DHL Order ID
     */
    public function __construct(
        private readonly bool $success,
        private readonly array $courierOrderIds = [],
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
     * Get courier order IDs returned by DHL WebAPI2, in request order.
     *
     * @return string[]
     */
    public function getCourierOrderIds(): array
    {
        return $this->courierOrderIds;
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
