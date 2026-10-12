<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Config\Source;

use DhlPoland\Dhl\Model\Config\Source\PricingType;
use PHPUnit\Framework\TestCase;

class PricingTypeTest extends TestCase
{
    /**
     * @var PricingType
     */
    private PricingType $pricingType;

    protected function setUp(): void
    {
        $this->pricingType = new PricingType();
    }

    public function testToOptionArrayContainsExpectedTypes(): void
    {
        $options = $this->pricingType->toOptionArray();
        $values = array_column($options, 'value');

        $this->assertContains('fixed', $values);
        $this->assertContains('weight', $values);
        $this->assertContains('subtotal', $values);
        $this->assertNotContains('country', $values);
    }
}
