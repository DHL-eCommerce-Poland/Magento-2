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
        self::assertTrue($catalog->isCodMethod('dhlpoland_parcel_polska_cod'));
        self::assertTrue($catalog->isCodMethod('dhlpoland_economy_all_cod'));
        self::assertTrue($catalog->isCodMethod('dhlpoland_economy_pop_cod'));
        self::assertTrue($catalog->isCodMethod('dhlpoland_economy_box_cod'));

        self::assertFalse($catalog->isCodMethod('parcel_polska'));
        self::assertFalse($catalog->isCodMethod('dhlpoland_parcel_polska'));
        self::assertFalse($catalog->isCodMethod('dhlpoland_economy_all'));
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
        self::assertSame('AH', $catalog->getServiceCodeForMethod('dhlpoland_parcel_polska_cod'));
        self::assertSame('PR', $catalog->getServiceCodeForMethod('dhlpoland_premium_cod'));
        self::assertSame('09', $catalog->getServiceCodeForMethod('dhlpoland_parcel_9_cod'));
        self::assertSame('12', $catalog->getServiceCodeForMethod('dhlpoland_parcel_12_cod'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlpoland_economy_all'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlpoland_economy_all_cod'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlpoland_economy_pop'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlpoland_economy_pop_cod'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlpoland_economy_box'));
        self::assertSame('SP', $catalog->getServiceCodeForMethod('dhlpoland_economy_box_cod'));
        self::assertSame('EK', $catalog->getServiceCodeForMethod('dhlpoland_connect_cod'));
        self::assertSame('EK', $catalog->getServiceCodeForMethod('dhlpoland_connect_parcelshop_cod'));
        self::assertSame('EK', $catalog->getServiceCodeForMethod('dhlpoland_connect_packstation_cod'));
        self::assertSame('CP', $catalog->getServiceCodeForMethod('dhlpoland_connect_plus_cod'));
        self::assertSame('CM', $catalog->getServiceCodeForMethod('dhlpoland_connect_pallet_cod'));
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

    /**
     * Verify international point type resolution handles carrier prefixes, COD variants, and unknown methods.
     */
    public function testInternationalMapPointTypeResolution(): void
    {
        $catalog = new MethodCatalog();

        self::assertSame('courier', $catalog->getInternationalMapPointTypeForMethod('dhlpoland_connect'));
        self::assertSame('courier', $catalog->getInternationalMapPointTypeForMethod('connect_cod'));
        self::assertSame(
            'parcelShop',
            $catalog->getInternationalMapPointTypeForMethod('dhlpoland_connect_parcelshop_cod')
        );
        self::assertSame(
            'packStation',
            $catalog->getInternationalMapPointTypeForMethod('dhlpoland_connect_packstation_cod')
        );
        self::assertSame('courier', $catalog->getInternationalMapPointTypeForMethod('unknown_method'));
        self::assertSame('courier', $catalog->getInternationalMapPointTypeForMethod(null));
    }

    /**
     * Verify getMethods returns the expected localized titles for all shipping methods.
     */
    public function testGetMethodsReturnsExpectedTitles(): void
    {
        $catalog = new MethodCatalog();
        $titles = [];
        foreach ($catalog->getMethods() as $method) {
            $titles[$method['code']] = (string)$method['title'];
        }

        $expected = [
            'parcel_polska' => 'Kurier DHL',
            'parcel_polska_cod' => 'Kurier DHL - płatność przy odbiorze',
            'premium' => 'Kurier DHL Premium',
            'premium_cod' => 'Kurier DHL Premium - płatność przy odbiorze',
            'parcel_9' => 'Kurier DHL – gwarancja dostawy do godziny 9:00',
            'parcel_9_cod' => 'Kurier DHL – gwarancja dostawy do godziny 9:00 - płatność przy odbiorze',
            'parcel_12' => 'Kurier DHL – gwarancja dostawy do godziny 12:00',
            'parcel_12_cod' => 'Kurier DHL – gwarancja dostawy do godziny 12:00 - płatność przy odbiorze',
            'economy_all' => 'Automaty DHL BOX i punkty POP (Dostawa także w soboty)',
            'economy_all_cod' => 'Automaty DHL BOX i punkty POP (Dostawa także w soboty) - płatność przy odbiorze',
            'economy_pop' => 'DHL POP (Dostawa także w soboty)',
            'economy_pop_cod' => 'DHL POP (Dostawa także w soboty) - płatność przy odbiorze',
            'economy_box' => 'Automaty DHL BOX (Dostawa także w soboty)',
            'economy_box_cod' => 'Automaty DHL BOX (Dostawa także w soboty) - płatność przy odbiorze',
            'max_paleta' => 'Kurier DHL - przesyłka paletowa',
            'max_paleta_cod' => 'Kurier DHL - przesyłka paletowa - płatność przy odbiorze',
            'connect' => 'DHL Connect',
            'connect_cod' => 'DHL Connect - Cash on Delivery',
            'connect_parcelshop' => 'DHL Connect Parcelshop',
            'connect_parcelshop_cod' => 'DHL Connect Parcelshop - Cash on Delivery',
            'connect_packstation' => 'DHL Connect Packstation',
            'connect_packstation_cod' => 'DHL Connect Packstation - Cash on Delivery',
            'connect_plus' => 'DHL Connect Plus',
            'connect_plus_cod' => 'DHL Connect Plus - Cash on Delivery',
            'connect_pallet' => 'DHL Connect Pallet',
            'connect_pallet_cod' => 'DHL Connect Pallet - Cash on Delivery',
        ];

        self::assertSame($expected, $titles);
    }
}
