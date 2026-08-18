<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\ContentSource;
use PHPUnit\Framework\TestCase;

class ContentSourceTest extends TestCase
{
    public function testToOptionArrayReturnsContentOptions(): void
    {
        $source = new ContentSource();
        $options = $source->toOptionArray();

        self::assertCount(4, $options);
        self::assertSame('ORDER_NUMBER', $options[0]['value']);
        self::assertSame('ORDER_ID', $options[1]['value']);
        self::assertSame('INVOICE_NUMBER', $options[2]['value']);
        self::assertSame('STATIC_VALUE', $options[3]['value']);
    }
}
