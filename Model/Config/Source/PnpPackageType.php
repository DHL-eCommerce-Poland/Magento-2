<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PnpPackageType implements OptionSourceInterface
{
    public const TYPE_EX = 'EX';
    public const TYPE_DR = 'DR';
    public const TYPE_ALL = 'ALL';
    public const TYPE_2EUROPE = '2EUROPE';
    public const TYPE_PR = 'PR';

    /**
     * Return PnP Package Type option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_EX, 'label' => __('EX - Domestic express up to 31.5kg')],
            ['value' => self::TYPE_DR, 'label' => __('DR - Domestic general cargo')],
            ['value' => self::TYPE_ALL, 'label' => __('ALL - All package types')],
            ['value' => self::TYPE_2EUROPE, 'label' => __('2EUROPE - Export shipments')],
            ['value' => self::TYPE_PR, 'label' => __('PR - Premium shipments')],
        ];
    }
}
