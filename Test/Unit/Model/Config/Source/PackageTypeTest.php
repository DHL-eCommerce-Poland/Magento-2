<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\PackageType;
use PHPUnit\Framework\TestCase;

class PackageTypeTest extends TestCase
{
    public function testToOptionArrayReturnsPackageTypes(): void
    {
        $source = new PackageType();
        $options = $source->toOptionArray();

        self::assertCount(3, $options);
        self::assertSame('PACKAGE', $options[0]['value']);
        self::assertSame('ENVELOPE', $options[1]['value']);
        self::assertSame('PALLET', $options[2]['value']);
    }
}
