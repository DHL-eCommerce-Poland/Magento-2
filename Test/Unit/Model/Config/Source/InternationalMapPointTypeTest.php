<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\InternationalMapPointType;
use PHPUnit\Framework\TestCase;

class InternationalMapPointTypeTest extends TestCase
{
    /**
     * @var InternationalMapPointType
     */
    private InternationalMapPointType $source;

    protected function setUp(): void
    {
        $this->source = new InternationalMapPointType();
    }

    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $options = $this->source->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertSame(InternationalMapPointType::TYPE_COURIER, $options[0]['value']);
        $this->assertSame(InternationalMapPointType::TYPE_PACKSTATION, $options[1]['value']);
        $this->assertSame(InternationalMapPointType::TYPE_PARCELSHOP, $options[2]['value']);
    }
}
