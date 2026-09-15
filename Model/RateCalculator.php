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
     * @param CountryRateResolver $countryRateResolver
     */
    public function __construct(
        private readonly TableRateResolver $tableRateResolver,
        private readonly CountryRateResolver $countryRateResolver
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
     * @param string|null $destCountryId
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
        ?string $destCountryId = null
    ): ?float {
        if ($isFreeShipping || ($isFreeShippingEnabled && $packageValue >= $freeShippingSubtotal)) {
            return 0.00;
        }

        if ($pricingType === self::PRICING_TYPE_COUNTRY) {
            if (empty($destCountryId)) {
                return null;
            }

            return $this->countryRateResolver->resolveRate($websiteId, $methodCode, $destCountryId);
        }

        if ($pricingType === self::PRICING_TYPE_WEIGHT) {
            $ratePrice = $this->tableRateResolver->resolveRate(
                $websiteId,
                $methodCode,
                self::PRICING_TYPE_WEIGHT,
                $packageWeight
            );

            return $ratePrice ?? 0.00;
        }

        if ($pricingType === self::PRICING_TYPE_SUBTOTAL) {
            $ratePrice = $this->tableRateResolver->resolveRate(
                $websiteId,
                $methodCode,
                self::PRICING_TYPE_SUBTOTAL,
                $packageValue
            );

            return $ratePrice ?? 0.00;
        }

        return $flatPrice;
    }
}
