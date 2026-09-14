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

        self::assertCount(11, $methods);
        self::assertArrayNotHasKey('group', $methods[0]);
        self::assertSame(
            [
                'parcel_polska',
                'premium',
                'parcel_9',
                'parcel_12',
                'economy',
                'max_paleta',
                'connect',
                'connect_parcel_shop',
                'connect_packstation',
                'connect_plus',
                'connect_pallet',
            ],
            array_column($methods, 'code')
        );
    }
}
