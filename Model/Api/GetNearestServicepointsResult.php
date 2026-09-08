<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

class GetNearestServicepointsResult
{
    /**
     * @param bool $success
     * @param array $points Array of points
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly array $points = [],
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
     * Get list of returned service points.
     *
     * @return array
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * Get the first (closest) service point from results.
     *
     * @return array|null
     */
    public function getClosestPoint(): ?array
    {
        if (empty($this->points)) {
            return null;
        }

        return $this->points[0] ?? null;
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
