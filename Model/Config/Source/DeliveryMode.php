<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DeliveryMode implements OptionSourceInterface
{
    public const MODE_MAP = 'map';
    public const MODE_NEAREST = 'nearest';

    /**
     * Return options array for delivery mode selection.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::MODE_MAP,
                'label' => __('Point selection from map and delivery'),
            ],
            [
                'value' => self::MODE_NEAREST,
                'label' => __('Delivery to nearest point based on recipient address'),
            ],
        ];
    }
}
