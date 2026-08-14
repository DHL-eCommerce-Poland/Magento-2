<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PricingType implements OptionSourceInterface
{
    /**
     * Return options array for pricing type selection.
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'fixed', 'label' => __('Fixed Price')],
            ['value' => 'weight', 'label' => __('Table Rates: By Weight')],
            ['value' => 'subtotal', 'label' => __('Table Rates: By Subtotal')],
        ];
    }
}
