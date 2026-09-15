<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\InternationalPricingType;
use PHPUnit\Framework\TestCase;

class InternationalPricingTypeTest extends TestCase
{
    private InternationalPricingType $pricingType;

    protected function setUp(): void
    {
        $this->pricingType = new InternationalPricingType();
    }

    public function testToOptionArrayContainsExpectedTypes(): void
    {
        $options = $this->pricingType->toOptionArray();
        $values = array_column($options, 'value');

        $this->assertSame(['fixed', 'weight', 'country', 'subtotal'], $values);

        $labels = array_column($options, 'label', 'value');
        $this->assertEquals(__('Table Rates: By Country'), $labels['country']);
    }
}
