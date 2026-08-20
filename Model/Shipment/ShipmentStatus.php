<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

class ShipmentStatus
{
    public const DISPATCHED = 'dispatched';
    public const PENDING_VERIFICATION = 'pending_verification';
    public const ERROR_SYNC = 'error_sync';
    public const CANCELLED = 'cancelled';

    /**
     * Get all available shipment statuses.
     *
     * @return string[]
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function getAllStatuses(): array
    {
        return [
            self::DISPATCHED,
            self::PENDING_VERIFICATION,
            self::ERROR_SYNC,
            self::CANCELLED,
        ];
    }
}
