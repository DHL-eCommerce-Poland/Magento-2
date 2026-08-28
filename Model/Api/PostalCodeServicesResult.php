<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class PostalCodeServicesResult
{
    /**
     * @param bool $success
     * @param string|null $drPickupFrom
     * @param string|null $drPickupTo
     * @param string|null $exPickupFrom
     * @param string|null $exPickupTo
     * @param bool $pickupOnSaturday
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $drPickupFrom = null,
        private readonly ?string $drPickupTo = null,
        private readonly ?string $exPickupFrom = null,
        private readonly ?string $exPickupTo = null,
        private readonly bool $pickupOnSaturday = false,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Check if request was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get domestic pickup start time (HH:MM).
     *
     * @return string|null
     */
    public function getDrPickupFrom(): ?string
    {
        return $this->drPickupFrom;
    }

    /**
     * Get domestic pickup end time (HH:MM).
     *
     * @return string|null
     */
    public function getDrPickupTo(): ?string
    {
        return $this->drPickupTo;
    }

    /**
     * Get express pickup start time (HH:MM).
     *
     * @return string|null
     */
    public function getExPickupFrom(): ?string
    {
        return $this->exPickupFrom;
    }

    /**
     * Get express pickup end time (HH:MM).
     *
     * @return string|null
     */
    public function getExPickupTo(): ?string
    {
        return $this->exPickupTo;
    }

    /**
     * Check if Saturday pickup is available.
     *
     * @return bool
     */
    public function isPickupOnSaturday(): bool
    {
        return $this->pickupOnSaturday;
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
}
