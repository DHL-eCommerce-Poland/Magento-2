<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

class InternationalPricingType extends PricingType
{
    /**
     * Return options array for international pricing type selection.
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CODE_FIXED, 'label' => __('Fixed Price')],
            ['value' => self::CODE_WEIGHT, 'label' => __('Table Rates: By Weight')],
            ['value' => self::CODE_COUNTRY, 'label' => __('Table Rates: By Country')],
            ['value' => self::CODE_SUBTOTAL, 'label' => __('Table Rates: By Subtotal')],
        ];
    }
}
