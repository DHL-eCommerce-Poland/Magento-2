<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\MethodCatalog;
use PHPUnit\Framework\TestCase;

class MethodCatalogTest extends TestCase
{
    /**
     * Verify the catalog contains the expected services and COD variants.
     */
    public function testCatalogContainsAllMethods(): void
    {
        $catalog = new MethodCatalog();
        $methods = $catalog->getMethods();

        self::assertCount(26, $methods);
        self::assertArrayNotHasKey('group', $methods[0]);
        self::assertSame(
            [
                'parcel_polska',
                'parcel_polska_cod',
                'premium',
                'premium_cod',
                'parcel_9',
                'parcel_9_cod',
                'parcel_12',
                'parcel_12_cod',
                'economy_all',
                'economy_all_cod',
                'economy_pop',
                'economy_pop_cod',
                'economy_box',
                'economy_box_cod',
                'max_paleta',
                'max_paleta_cod',
                'connect',
                'connect_cod',
                'connect_parcelshop',
                'connect_parcelshop_cod',
                'connect_packstation',
                'connect_packstation_cod',
                'connect_plus',
                'connect_plus_cod',
                'connect_pallet',
                'connect_pallet_cod',
            ],
            array_column($methods, 'code')
        );
    }

    /**
     * Verify isCodMethod correctly identifies COD methods.
     */
    public function testIsCodMethod(): void
    {
        $catalog = new MethodCatalog();

        self::assertTrue($catalog->isCodMethod('parcel_polska_cod'));
        self::assertTrue($catalog->isCodMethod('dhlparcel_parcel_polska_cod'));
        self::assertTrue($catalog->isCodMethod('dhlparcel_economy_all_cod'));
        self::assertTrue($catalog->isCodMethod('dhlparcel_economy_pop_cod'));
        self::assertTrue($catalog->isCodMethod('dhlparcel_economy_box_cod'));

        self::assertFalse($catalog->isCodMethod('parcel_polska'));
        self::assertFalse($catalog->isCodMethod('dhlparcel_parcel_polska'));
        self::assertFalse($catalog->isCodMethod('dhlparcel_economy_all'));
        self::assertFalse($catalog->isCodMethod(null));
        self::assertFalse($catalog->isCodMethod(''));
    }

    /**
     * Verify getServiceCodeForMethod resolves service codes for COD methods.
     */
    public function testGetServiceCodeForMethodResolvesCodMethods(): void
    {
        $catalog = new MethodCatalog();

        self::assertSame('AH', $catalog->getServiceCodeForMethod('parcel_polska_cod'));
        self::assertSame('AH', $catalog->getServiceCodeForMethod('dhlparcel_parcel_polska_cod'));
        self::assertSame('PR', $catalog->getServiceCodeForMethod('dhlparcel_premium_cod'));
        self::assertSame('09', $catalog->getServiceCodeForMethod('dhlparcel_parcel_9_cod'));
        self::assertSame('12', $catalog->getServiceCodeForMethod('dhlparcel_parcel_12_cod'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlparcel_economy_all'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlparcel_economy_all_cod'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlparcel_economy_pop'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlparcel_economy_pop_cod'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlparcel_economy_box'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlparcel_economy_box_cod'));
        self::assertSame('EK', $catalog->getServiceCodeForMethod('dhlparcel_connect_cod'));
        self::assertSame('EK', $catalog->getServiceCodeForMethod('dhlparcel_connect_parcelshop_cod'));
        self::assertSame('EK', $catalog->getServiceCodeForMethod('dhlparcel_connect_packstation_cod'));
        self::assertSame('CP', $catalog->getServiceCodeForMethod('dhlparcel_connect_plus_cod'));
        self::assertSame('CM', $catalog->getServiceCodeForMethod('dhlparcel_connect_pallet_cod'));
    }

    /**
     * Verify pickup-point methods are classified consistently by service point type.
     */
    public function testServicePointMethodClassification(): void
    {
        $catalog = new MethodCatalog();

        $this->assertTrue($catalog->isServicePointMethod('economy_all'));
        $this->assertTrue($catalog->isServicePointMethod('economy_all_cod'));
        $this->assertTrue($catalog->isServicePointMethod('economy_pop'));
        $this->assertTrue($catalog->isServicePointMethod('economy_pop_cod'));
        $this->assertTrue($catalog->isServicePointMethod('economy_box'));
        $this->assertTrue($catalog->isServicePointMethod('economy_box_cod'));
        $this->assertTrue($catalog->isServicePointMethod('connect_parcelshop'));
        $this->assertTrue($catalog->isServicePointMethod('connect_parcelshop_cod'));
        $this->assertTrue($catalog->isServicePointMethod('connect_packstation'));
        $this->assertTrue($catalog->isServicePointMethod('connect_packstation_cod'));
        $this->assertFalse($catalog->isServicePointMethod('connect'));
        $this->assertFalse($catalog->isServicePointMethod('courier'));

        $this->assertTrue($catalog->isPackstationMethod('connect_packstation'));
        $this->assertTrue($catalog->isPackstationMethod('connect_packstation_cod'));
        $this->assertFalse($catalog->isPackstationMethod('connect_parcelshop'));

        $this->assertTrue($catalog->isParcelShopMethod('connect_parcelshop'));
        $this->assertTrue($catalog->isParcelShopMethod('connect_parcelshop_cod'));
        $this->assertFalse($catalog->isParcelShopMethod('connect_packstation'));
    }
}
