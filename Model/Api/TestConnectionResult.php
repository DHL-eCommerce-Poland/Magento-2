<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class TestConnectionResult
{
    /**
     * @param bool $isSuccess
     * @param string $message
     * @param string|null $errorCode
     */
    public function __construct(
        private readonly bool $isSuccess,
        private readonly string $message,
        private readonly ?string $errorCode = null
    ) {
    }

    /**
     * Is connection test successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * Get result message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get optional error code.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
