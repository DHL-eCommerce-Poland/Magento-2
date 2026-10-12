<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PackageType implements OptionSourceInterface
{
    public const PACKAGE = 'PACKAGE';
    public const ENVELOPE = 'ENVELOPE';
    public const PALLET = 'PALLET';

    /**
     * Return Package Type option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PACKAGE, 'label' => __('Package')],
            ['value' => self::ENVELOPE, 'label' => __('Envelope')],
            ['value' => self::PALLET, 'label' => __('Pallet')],
        ];
    }
}
