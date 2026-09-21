<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment\Data;

use Ideo\Dhl\Model\Shipment\Data\PackageData;
use PHPUnit\Framework\TestCase;

class PackageDataTest extends TestCase
{
    /**
     * Test constructor parameter assignment and getter methods.
     *
     * @return void
     */
    public function testConstructAndGetters(): void
    {
        $package = new PackageData(
            packageType: 'PACKAGE',
            quantity: 2,
            weight: 2.5,
            width: 20.0,
            length: 30.0,
            height: 15.0,
            isNonStandard: true,
            hasEuroReturn: false
        );

        $this->assertEquals('PACKAGE', $package->getPackageType());
        $this->assertEquals(2, $package->getQuantity());
        $this->assertEquals(2.5, $package->getWeight());
        $this->assertEquals(20.0, $package->getWidth());
        $this->assertEquals(30.0, $package->getLength());
        $this->assertEquals(15.0, $package->getHeight());
        $this->assertTrue($package->isNonStandard());
        $this->assertFalse($package->hasEuroReturn());
    }

    /**
     * Test array serialization and deserialization via toArray and fromArray.
     *
     * @return void
     */
    public function testToArrayAndFromArray(): void
    {
        $data = [
            'packageType' => 'PALLET',
            'quantity' => 1,
            'weight' => 150.0,
            'width' => 80.0,
            'length' => 120.0,
            'height' => 180.0,
            'isNonStandard' => false,
            'hasEuroReturn' => true,
        ];

        $package = PackageData::fromArray($data);
        $this->assertEquals('PALLET', $package->getPackageType());
        $this->assertEquals(150.0, $package->getWeight());
        $this->assertTrue($package->hasEuroReturn());

        $exported = $package->toArray();
        $this->assertEquals($data, $exported);
    }

    /**
     * Test that hasEuroReturn is reset to false when packageType is not PALLET.
     *
     * @return void
     */
    public function testHasEuroReturnIgnoredForNonPallet(): void
    {
        $package = new PackageData(
            packageType: 'PACKAGE',
            hasEuroReturn: true
        );

        $this->assertFalse($package->hasEuroReturn());
    }

    /**
     * Test fromArray with snake_case keys as stored in database JSON.
     *
     * @return void
     */
    public function testFromArrayWithSnakeCaseKeys(): void
    {
        $data = [
            'package_type' => 'PALLET',
            'quantity' => '2',
            'weight' => '25.5',
            'width' => '60',
            'length' => '80',
            'height' => '90',
            'is_non_standard' => '1',
            'has_euro_return' => '1',
        ];

        $package = PackageData::fromArray($data);
        $this->assertEquals('PALLET', $package->getPackageType());
        $this->assertEquals(2, $package->getQuantity());
        $this->assertEquals(25.5, $package->getWeight());
        $this->assertEquals(60.0, $package->getWidth());
        $this->assertEquals(80.0, $package->getLength());
        $this->assertEquals(90.0, $package->getHeight());
        $this->assertTrue($package->isNonStandard());
        $this->assertTrue($package->hasEuroReturn());
    }
}
