<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Source;

use Ideo\Dhl\Model\Source\CustomsType;
use PHPUnit\Framework\TestCase;

class CustomsTypeTest extends TestCase
{
    /**
     * @var CustomsType
     */
    private CustomsType $source;

    protected function setUp(): void
    {
        $this->source = new CustomsType();
    }

    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $options = $this->source->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertEquals(
            [
                ['value' => CustomsType::TYPE_SIMPLIFIED, 'label' => __('Simplified Clearance (U)')],
                ['value' => CustomsType::TYPE_INDIVIDUAL, 'label' => __('Individual Clearance (I)')],
                ['value' => CustomsType::TYPE_SHORT_DECLARATION, 'label' => __('Short Declaration - IE/UK (S)')],
            ],
            $options
        );
    }
}
