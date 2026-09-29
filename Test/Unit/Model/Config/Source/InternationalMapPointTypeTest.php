<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\InternationalMapPointType;
use PHPUnit\Framework\TestCase;

class InternationalMapPointTypeTest extends TestCase
{
    private InternationalMapPointType $source;

    protected function setUp(): void
    {
        $this->source = new InternationalMapPointType();
    }

    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $options = $this->source->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertEquals(InternationalMapPointType::TYPE_PACKSTATION, $options[0]['value']);
        $this->assertEquals(InternationalMapPointType::TYPE_PARCELSHOP, $options[1]['value']);
        $this->assertEquals(InternationalMapPointType::TYPE_COURIER, $options[2]['value']);
    }
}
