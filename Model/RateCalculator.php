<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

class RateCalculator
{
    public const PRICING_TYPE_FIXED = 'fixed';
    public const PRICING_TYPE_COUNTRY = 'country';
    public const PRICING_TYPE_WEIGHT = 'weight';
    public const PRICING_TYPE_SUBTOTAL = 'subtotal';

    /**
     * @param TableRateResolver $tableRateResolver
     */
    public function __construct(
        private readonly TableRateResolver $tableRateResolver
    ) {
    }

    /**
     * Calculate shipping rate price based on pricing type and quote metrics.
     *
     * @param int $websiteId
     * @param string $methodCode
     * @param string $pricingType
     * @param float $packageWeight
     * @param float $packageValue
     * @param float $flatPrice
     * @param bool $isFreeShippingEnabled
     * @param float $freeShippingSubtotal
     * @param bool $isFreeShipping
     * @param string $destCountryId
     *
     * @return float|null Null when shipping method is unavailable for the destination.
     */
    public function calculate(
        int $websiteId,
        string $methodCode,
        string $pricingType,
        float $packageWeight,
        float $packageValue,
        float $flatPrice,
        bool $isFreeShippingEnabled,
        float $freeShippingSubtotal,
        bool $isFreeShipping,
        string $destCountryId = '*'
    ): ?float {
        if ($isFreeShipping || ($isFreeShippingEnabled && $packageValue >= $freeShippingSubtotal)) {
            return 0.00;
        }

        if ($pricingType === self::PRICING_TYPE_FIXED) {
            return $flatPrice;
        }

        if ($pricingType === self::PRICING_TYPE_WEIGHT || $pricingType === self::PRICING_TYPE_COUNTRY) {
            return $this->tableRateResolver->resolveRate(
                $websiteId,
                $methodCode,
                $destCountryId,
                self::PRICING_TYPE_WEIGHT,
                $packageWeight
            );
        }

        if ($pricingType === self::PRICING_TYPE_SUBTOTAL) {
            return $this->tableRateResolver->resolveRate(
                $websiteId,
                $methodCode,
                $destCountryId,
                self::PRICING_TYPE_SUBTOTAL,
                $packageValue
            );
        }

        return $flatPrice;
    }
}
