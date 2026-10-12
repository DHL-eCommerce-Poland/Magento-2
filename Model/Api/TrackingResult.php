<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Api;

class TrackingResult
{
    /**
     * @param bool $success
     * @param string|null $shipmentId
     * @param string|null $receivedBy
     * @param array $events
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $shipmentId = null,
        private readonly ?string $receivedBy = null,
        private readonly array $events = [],
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null
    ) {
    }

    /**
     * Whether the tracking request succeeded.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the DHL shipment ID.
     *
     * @return string|null
     */
    public function getShipmentId(): ?string
    {
        return $this->shipmentId;
    }

    /**
     * Get person who received the shipment.
     *
     * @return string|null
     */
    public function getReceivedBy(): ?string
    {
        return $this->receivedBy;
    }

    /**
     * Get tracking events array.
     *
     * @return array
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Get latest event status description.
     *
     * @return string|null
     */
    public function getLatestStatusDescription(): ?string
    {
        if (empty($this->events)) {
            return null;
        }

        $lastKey = array_key_last($this->events);
        $lastEvent = $lastKey !== null ? $this->events[$lastKey] : null;

        return !empty($lastEvent['description']) ? (string) $lastEvent['description'] : null;
    }

    /**
     * Get latest event status code.
     *
     * @return string|null
     */
    public function getLatestStatusCode(): ?string
    {
        if (empty($this->events)) {
            return null;
        }

        $lastKey = array_key_last($this->events);
        $lastEvent = $lastKey !== null ? $this->events[$lastKey] : null;

        return !empty($lastEvent['status']) ? (string) $lastEvent['status'] : null;
    }

    /**
     * Get error code if tracking failed.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get error message if tracking failed.
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
