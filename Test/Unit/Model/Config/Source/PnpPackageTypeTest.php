<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\PnpPackageType;
use PHPUnit\Framework\TestCase;

class PnpPackageTypeTest extends TestCase
{
    public function testToOptionArrayReturnsPnpPackageTypes(): void
    {
        $source = new PnpPackageType();
        $options = $source->toOptionArray();

        $this->assertCount(5, $options);
        $values = array_column($options, 'value');
        $this->assertEquals(['EX', 'DR', 'ALL', '2EUROPE', 'PR'], $values);
    }
}
