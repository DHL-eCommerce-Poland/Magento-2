<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MapPointType implements OptionSourceInterface
{
    public const TYPE_ALL = 'all';
    public const TYPE_BOX = 'box';
    public const TYPE_POP = 'pop';

    /**
     * Return options array for map point type selection.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::TYPE_ALL,
                'label' => __('Map containing all points'),
            ],
            [
                'value' => self::TYPE_BOX,
                'label' => __('Only DHL BOX'),
            ],
            [
                'value' => self::TYPE_POP,
                'label' => __('Only DHL POPs'),
            ],
        ];
    }
}
