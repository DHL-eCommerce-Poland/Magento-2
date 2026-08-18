<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\LabelType;
use PHPUnit\Framework\TestCase;

class LabelTypeTest extends TestCase
{
    public function testToOptionArrayReturnsLabelTypes(): void
    {
        $source = new LabelType();
        $options = $source->toOptionArray();

        self::assertCount(4, $options);
        self::assertSame('BLP', $options[0]['value']);
        self::assertSame('LBLP', $options[1]['value']);
        self::assertSame('ZBLP', $options[2]['value']);
        self::assertSame('ZBLP300', $options[3]['value']);
    }
}
