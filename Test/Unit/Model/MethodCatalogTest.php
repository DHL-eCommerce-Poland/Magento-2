<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\MethodCatalog;
use PHPUnit\Framework\TestCase;

class MethodCatalogTest extends TestCase
{
    /**
     * Verify the catalog contains the expected services.
     */
    public function testCatalogContainsAllMethods(): void
    {
        $methods = (new MethodCatalog())->getMethods();

        self::assertCount(15, $methods);
        self::assertArrayNotHasKey('group', $methods[0]);
        self::assertSame(
            [
                'parcel_polska',
                'premium',
                '9',
                '12',
                'return_polska',
                'economy',
                'max_paczka',
                'max_polpaleta',
                'max_paleta',
                'connect',
                'international',
                'connect_plus',
                'return_connect',
                'connect_polpaleta',
                'connect_pallet',
            ],
            array_column($methods, 'code')
        );
    }
}
