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
     * Strictly returns the longer human-readable Polish description, preferring the newest event by timestamp.
     *
     * @return string|null
     */
    public function getLatestStatusDescription(): ?string
    {
        if (empty($this->events)) {
            return null;
        }

        $bestEvent = null;
        $bestTimestamp = -1;

        foreach ($this->events as $event) {
            if (!is_array($event) || empty(trim((string) ($event['description'] ?? '')))) {
                continue;
            }

            $rawTimestamp = (string) ($event['timestamp'] ?? '');
            $timestamp = $rawTimestamp !== '' ? strtotime($rawTimestamp) : false;

            if ($timestamp !== false && $timestamp > 0) {
                if ($timestamp >= $bestTimestamp) {
                    $bestTimestamp = $timestamp;
                    $bestEvent = $event;
                }
            } elseif ($bestTimestamp <= 0) {
                $bestEvent = $event;
            }
        }

        return !empty($bestEvent['description']) ? (string) $bestEvent['description'] : null;
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
