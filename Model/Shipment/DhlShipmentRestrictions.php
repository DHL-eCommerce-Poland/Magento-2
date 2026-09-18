<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\MethodCatalog;

class DhlShipmentRestrictions
{
    public const MAX_LENGTH_CONTENT = 30;
    public const MAX_LENGTH_COMMENT = 100;
    public const MAX_LENGTH_REFERENCE = 100;

    /**
     * Product codes that restrict shipments to a single piece (1 package).
     */
    public const SINGLE_PIECE_PRODUCTS = [
        MethodCatalog::SERVICE_SP,
        MethodCatalog::SERVICE_PS,
        MethodCatalog::SERVICE_LM,
    ];

    /**
     * Product codes that prohibit non-standard (NST) packages.
     */
    public const NO_NON_STANDARD_PRODUCTS = [
        MethodCatalog::SERVICE_SP,
        MethodCatalog::SERVICE_PS,
        MethodCatalog::SERVICE_LM,
    ];

    /**
     * Check if given product code or popId restricts shipment to a single piece.
     *
     * @param string|null $serviceType
     * @param string|null $popId
     *
     * @return bool
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function isSinglePieceOnly(?string $serviceType, ?string $popId = null): bool
    {
        if (!empty($popId)) {
            return true;
        }

        if ($serviceType === null || trim($serviceType) === '') {
            return false;
        }

        return in_array(strtoupper(trim($serviceType)), self::SINGLE_PIECE_PRODUCTS, true);
    }

    /**
     * Check if given product code or popId prohibits non-standard (NST) packages.
     *
     * @param string|null $serviceType
     * @param string|null $popId
     *
     * @return bool
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function isNonStandardForbidden(?string $serviceType, ?string $popId = null): bool
    {
        if (!empty($popId)) {
            return true;
        }

        if ($serviceType === null || trim($serviceType) === '') {
            return false;
        }

        return in_array(strtoupper(trim($serviceType)), self::NO_NON_STANDARD_PRODUCTS, true);
    }
}
