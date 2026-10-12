<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Config\Source;

use DhlPoland\Dhl\Model\Config\Source\AddressType;
use PHPUnit\Framework\TestCase;

class AddressTypeTest extends TestCase
{
    public function testToOptionArrayReturnsAddressTypeOptions(): void
    {
        $source = new AddressType();
        $options = $source->toOptionArray();

        self::assertCount(3, $options);
        self::assertSame('N', $options[0]['value']);
        self::assertSame('C', $options[1]['value']);
        self::assertSame('B', $options[2]['value']);
    }
}
