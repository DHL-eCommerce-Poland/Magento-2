<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Config\Source;

use DhlPoland\Dhl\Model\Config\Source\ShipmentAddressType;
use PHPUnit\Framework\TestCase;

class ShipmentAddressTypeTest extends TestCase
{
    public function testToOptionArrayReturnsPrivateAndCompanyAddressOptions(): void
    {
        $source = new ShipmentAddressType();
        $options = $source->toOptionArray();

        $this->assertCount(2, $options);
        $this->assertEquals('C', $options[0]['value']);
        $this->assertEquals('B', $options[1]['value']);
    }
}
