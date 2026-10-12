<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CategoryOfItem implements OptionSourceInterface
{
    public const CATEGORY_SALES_OF_GOODS = '11';
    public const CATEGORY_OTHER = '9';
    public const CATEGORY_RETURN_OF_GOODS = '21';
    public const CATEGORY_GIFT = '31';
    public const CATEGORY_COMMERCIAL_SAMPLES = '32';
    public const CATEGORY_DOCUMENTS = '91';

    public const ALLOWED_CATEGORIES_BY_TYPE = [
        CustomsType::TYPE_INDIVIDUAL => [
            self::CATEGORY_OTHER,
            self::CATEGORY_SALES_OF_GOODS,
            self::CATEGORY_RETURN_OF_GOODS,
            self::CATEGORY_COMMERCIAL_SAMPLES,
        ],
        CustomsType::TYPE_SIMPLIFIED => [
            self::CATEGORY_OTHER,
            self::CATEGORY_SALES_OF_GOODS,
            self::CATEGORY_RETURN_OF_GOODS,
            self::CATEGORY_GIFT,
            self::CATEGORY_COMMERCIAL_SAMPLES,
            self::CATEGORY_DOCUMENTS,
        ],
        CustomsType::TYPE_SHORT_DECLARATION => [
            self::CATEGORY_OTHER,
            self::CATEGORY_SALES_OF_GOODS,
            self::CATEGORY_RETURN_OF_GOODS,
            self::CATEGORY_GIFT,
            self::CATEGORY_COMMERCIAL_SAMPLES,
            self::CATEGORY_DOCUMENTS,
        ],
    ];

    /**
     * Get options array for UI Components and configuration.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CATEGORY_OTHER, 'label' => (string)__('Other (9)')],
            ['value' => self::CATEGORY_SALES_OF_GOODS, 'label' => (string)__('Sales of Goods (11)')],
            ['value' => self::CATEGORY_RETURN_OF_GOODS, 'label' => (string)__('Return of Goods (21)')],
            ['value' => self::CATEGORY_GIFT, 'label' => (string)__('Gift (31)')],
            ['value' => self::CATEGORY_COMMERCIAL_SAMPLES, 'label' => (string)__('Patterns and Samples (32)')],
            ['value' => self::CATEGORY_DOCUMENTS, 'label' => (string)__('Documents (91)')],
        ];
    }

    /**
     * Get allowed category codes for a specific customs clearance type.
     *
     * @param string $customsType
     *
     * @return array
     */
    public function getCategoriesForType(string $customsType): array
    {
        return self::ALLOWED_CATEGORIES_BY_TYPE[$customsType] ?? [];
    }

    /**
     * Check if a category is permitted for a given customs clearance type.
     *
     * @param string $customsType
     * @param string $category
     *
     * @return bool
     */
    public function isCategoryAllowed(string $customsType, string $category): bool
    {
        $allowed = $this->getCategoriesForType($customsType);
        return in_array((string)$category, $allowed, true);
    }
}
