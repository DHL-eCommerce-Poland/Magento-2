<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AddressType implements OptionSourceInterface
{
    public const TYPE_BASED_ON_CART = 'N';
    public const TYPE_CONSUMER = 'C';
    public const TYPE_BUSINESS = 'B';

    /**
     * Return option array for Address Type.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_BASED_ON_CART, 'label' => __('Based on Cart')],
            ['value' => self::TYPE_CONSUMER, 'label' => __('Private Address')],
            ['value' => self::TYPE_BUSINESS, 'label' => __('Company Address')],
        ];
    }
}
