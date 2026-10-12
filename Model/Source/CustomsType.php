<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CustomsType implements OptionSourceInterface
{
    public const TYPE_SIMPLIFIED = 'U';
    public const TYPE_INDIVIDUAL = 'I';
    public const TYPE_SHORT_DECLARATION = 'S';

    /**
     * Get options array for UI Components and configuration.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_SIMPLIFIED, 'label' => __('Simplified Clearance (U)')],
            ['value' => self::TYPE_INDIVIDUAL, 'label' => __('Individual Clearance (I)')],
            ['value' => self::TYPE_SHORT_DECLARATION, 'label' => __('Short Declaration - IE/UK (S)')],
        ];
    }
}
