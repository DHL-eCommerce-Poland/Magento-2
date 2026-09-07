<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CategoryOfItem implements OptionSourceInterface
{
    public const CATEGORY_SALES_OF_GOODS = '11';
    public const CATEGORY_OTHER = '9';
    public const CATEGORY_RETURN_OF_GOODS = '21';
    public const CATEGORY_GIFT = '31';
    public const CATEGORY_COMMERCIAL_SAMPLES = '32';
    public const CATEGORY_DOCUMENTS = '91';

    /**
     * Get options array for UI Components and configuration.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CATEGORY_SALES_OF_GOODS, 'label' => __('Sales of Goods (11)')],
            ['value' => self::CATEGORY_OTHER, 'label' => __('Other (9)')],
            ['value' => self::CATEGORY_RETURN_OF_GOODS, 'label' => __('Return of Goods (21)')],
            ['value' => self::CATEGORY_GIFT, 'label' => __('Gift (31)')],
            ['value' => self::CATEGORY_COMMERCIAL_SAMPLES, 'label' => __('Patterns and Samples (32)')],
            ['value' => self::CATEGORY_DOCUMENTS, 'label' => __('Documents (91)')],
        ];
    }
}
