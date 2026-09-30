<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Source;

use Ideo\Dhl\Model\Source\CategoryOfItem;
use Ideo\Dhl\Model\Source\CustomsType;
use PHPUnit\Framework\TestCase;

class CategoryOfItemTest extends TestCase
{
    /**
     * @var CategoryOfItem
     */
    private CategoryOfItem $source;

    protected function setUp(): void
    {
        $this->source = new CategoryOfItem();
    }

    public function testToOptionArrayReturnsOrderedCategories(): void
    {
        $options = $this->source->toOptionArray();

        $this->assertCount(6, $options);
        $values = array_column($options, 'value');
        $this->assertSame(['9', '11', '21', '31', '32', '91'], $values);
    }

    public function testGetCategoriesForTypeIndividual(): void
    {
        $categories = $this->source->getCategoriesForType(CustomsType::TYPE_INDIVIDUAL);
        $this->assertSame(['9', '11', '21', '32'], $categories);
    }

    public function testGetCategoriesForTypeSimplified(): void
    {
        $categories = $this->source->getCategoriesForType(CustomsType::TYPE_SIMPLIFIED);
        $this->assertSame(['9', '11', '21', '31', '32', '91'], $categories);
    }

    public function testGetCategoriesForTypeShortDeclaration(): void
    {
        $categories = $this->source->getCategoriesForType(CustomsType::TYPE_SHORT_DECLARATION);
        $this->assertSame(['9', '11', '21', '31', '32', '91'], $categories);
    }

    public function testGetCategoriesForTypeInvalidReturnsEmpty(): void
    {
        $categories = $this->source->getCategoriesForType('NON_EXISTING');
        $this->assertSame([], $categories);
    }

    public function testIsCategoryAllowed(): void
    {
        $cases = [
            // Individual clearance (I): 9, 11, 21, 32 (no 31, no 91)
            'I - 9' => [CustomsType::TYPE_INDIVIDUAL, '9', true],
            'I - 11' => [CustomsType::TYPE_INDIVIDUAL, '11', true],
            'I - 21' => [CustomsType::TYPE_INDIVIDUAL, '21', true],
            'I - 32' => [CustomsType::TYPE_INDIVIDUAL, '32', true],
            'I - 31' => [CustomsType::TYPE_INDIVIDUAL, '31', false],
            'I - 91' => [CustomsType::TYPE_INDIVIDUAL, '91', false],

            // Simplified clearance (U): 9, 11, 21, 31, 32, 91
            'U - 9' => [CustomsType::TYPE_SIMPLIFIED, '9', true],
            'U - 11' => [CustomsType::TYPE_SIMPLIFIED, '11', true],
            'U - 21' => [CustomsType::TYPE_SIMPLIFIED, '21', true],
            'U - 31' => [CustomsType::TYPE_SIMPLIFIED, '31', true],
            'U - 32' => [CustomsType::TYPE_SIMPLIFIED, '32', true],
            'U - 91' => [CustomsType::TYPE_SIMPLIFIED, '91', true],

            // Short declaration (S): 9, 11, 21, 31, 32, 91
            'S - 9' => [CustomsType::TYPE_SHORT_DECLARATION, '9', true],
            'S - 11' => [CustomsType::TYPE_SHORT_DECLARATION, '11', true],
            'S - 21' => [CustomsType::TYPE_SHORT_DECLARATION, '21', true],
            'S - 31' => [CustomsType::TYPE_SHORT_DECLARATION, '31', true],
            'S - 32' => [CustomsType::TYPE_SHORT_DECLARATION, '32', true],
            'S - 91' => [CustomsType::TYPE_SHORT_DECLARATION, '91', true],

            // Unknown type
            'Unknown type' => ['UNKNOWN', '11', false],
        ];

        foreach ($cases as $name => [$type, $category, $expected]) {
            $this->assertSame(
                $expected,
                $this->source->isCategoryAllowed($type, $category),
                "Failed asserting category allowance for case: {$name}"
            );
        }
    }
}
