<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InternationalMapPointType implements OptionSourceInterface
{
    public const TYPE_PACKSTATION = 'packStation';
    public const TYPE_PARCELSHOP = 'parcelShop';
    public const TYPE_COURIER = 'courier';

    /**
     * Return options array for international map point type selection.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::TYPE_COURIER,
                'label' => __('Courier'),
            ],
            [
                'value' => self::TYPE_PACKSTATION,
                'label' => __('Packstation'),
            ],
            [
                'value' => self::TYPE_PARCELSHOP,
                'label' => __('Parcelshop'),
            ],
        ];
    }
}
