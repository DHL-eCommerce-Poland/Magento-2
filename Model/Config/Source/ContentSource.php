<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ContentSource implements OptionSourceInterface
{
    /**
     * Return option array for Package Contents Description.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'ORDER_NUMBER', 'label' => __('Order Number')],
            ['value' => 'ORDER_ID', 'label' => __('Order ID')],
            ['value' => 'INVOICE_NUMBER', 'label' => __('Invoice Number')],
            ['value' => 'STATIC_VALUE', 'label' => __('Static Value')],
        ];
    }
}
