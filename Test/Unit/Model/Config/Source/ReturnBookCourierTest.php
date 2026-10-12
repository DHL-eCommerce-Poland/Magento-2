<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Config\Source;

use DhlPoland\Dhl\Model\Config\Source\ReturnBookCourier;
use PHPUnit\Framework\TestCase;

class ReturnBookCourierTest extends TestCase
{
    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $source = new ReturnBookCourier();
        $options = $source->toOptionArray();

        self::assertCount(2, $options);
        self::assertSame('0', $options[0]['value']);
        self::assertSame('1', $options[1]['value']);
    }
}
