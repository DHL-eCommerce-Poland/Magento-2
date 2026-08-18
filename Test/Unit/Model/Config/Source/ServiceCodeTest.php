<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\ServiceCode;
use PHPUnit\Framework\TestCase;

class ServiceCodeTest extends TestCase
{
    public function testToOptionArrayReturnsDhlServiceCodes(): void
    {
        $source = new ServiceCode();
        $options = $source->toOptionArray();

        self::assertCount(10, $options);
        self::assertSame('', $options[0]['value']);
        self::assertSame('AH', $options[1]['value']);
        self::assertSame('09', $options[2]['value']);
        self::assertSame('12', $options[3]['value']);
        self::assertSame('PR', $options[4]['value']);
        self::assertSame('SP', $options[5]['value']);
        self::assertSame('EK', $options[6]['value']);
        self::assertSame('EKSP', $options[7]['value']);
        self::assertSame('CP', $options[8]['value']);
        self::assertSame('CM', $options[9]['value']);
    }
}
