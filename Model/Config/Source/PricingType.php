<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PricingType implements OptionSourceInterface
{
    public const CODE_FIXED = 'fixed';
    public const CODE_COUNTRY = 'country';
    public const CODE_WEIGHT = 'weight';
    public const CODE_SUBTOTAL = 'subtotal';

    /**
     * Return options array for pricing type selection.
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CODE_FIXED, 'label' => __('Fixed Price')],
            ['value' => self::CODE_WEIGHT, 'label' => __('Table Rates: By Weight')],
            ['value' => self::CODE_SUBTOTAL, 'label' => __('Table Rates: By Subtotal')],
        ];
    }
}
