<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Api;

class CancelCourierBookingResult
{
    /**
     * @param bool $success
     * @param string|null $dispatchNumber
     * @param string|null $errorCode
     * @param string|null $errorMessage
     * @param string[] $cancelledDispatchNumbers
     * @param array $errors
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $dispatchNumber = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly array $cancelledDispatchNumbers = [],
        private readonly array $errors = []
    ) {
    }

    /**
     * Check if cancellation was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get cancelled courier order / dispatch number.
     *
     * @return string|null
     */
    public function getDispatchNumber(): ?string
    {
        return $this->dispatchNumber;
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
     * Get list of successfully cancelled dispatch numbers.
     *
     * @return string[]
     */
    public function getCancelledDispatchNumbers(): array
    {
        return $this->cancelledDispatchNumbers;
    }

    /**
     * Get list or map of errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
