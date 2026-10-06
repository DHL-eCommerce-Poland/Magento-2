<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment\Data;

use Ideo\Dhl\Model\Shipment\Data\PackageData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use PHPUnit\Framework\TestCase;

class ShipmentSettingsDataTest extends TestCase
{
    /**
     * Test constructor parameter assignment and single package backwards compatibility getters.
     *
     * @return void
     */
    public function testConstructAndSinglePackageGetters(): void
    {
        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            pickupType: 'COURIER',
            content: 'Zawartosc przesyłki',
            mpk: 'MPK123',
            comment: 'Komentarz do zamowienia',
            reference: 'REF-001',
            shipmentDate: '2026-08-20',
            packageType: 'PACKAGE',
            weight: 3.5,
            width: 25.0,
            height: 15.0,
            length: 35.0,
            packageQuantity: 1,
            labelType: 'BLP',
            isNonStandard: false,
            hasEuroReturn: false,
            autoCreateReturnShipment: true,
            returnLabelType: 'BLP',
            returnQrCodeType: 'QR_PDF',
            returnLabelExpDays: 14,
            returnBookCourier: 0,
            dropOffPointId: 5,
            addressType: 'B'
        );

        $this->assertEquals('AH', $settings->getServiceType());
        $this->assertEquals('COURIER', $settings->getPickupType());
        $this->assertEquals('Zawartosc przesyłki', $settings->getContent());
        $this->assertEquals('MPK123', $settings->getMpk());
        $this->assertEquals('Komentarz do zamowienia', $settings->getComment());
        $this->assertEquals('REF-001', $settings->getReference());
        $this->assertEquals('2026-08-20', $settings->getShipmentDate());
        $this->assertEquals('PACKAGE', $settings->getPackageType());
        $this->assertEquals(3.5, $settings->getWeight());
        $this->assertEquals(25.0, $settings->getWidth());
        $this->assertEquals(15.0, $settings->getHeight());
        $this->assertEquals(35.0, $settings->getLength());
        $this->assertEquals(1, $settings->getPackageQuantity());
        $this->assertEquals('BLP', $settings->getLabelType());
        $this->assertFalse($settings->isNonStandard());
        $this->assertFalse($settings->hasEuroReturn());
        $this->assertTrue($settings->autoCreateReturnShipment());
        $this->assertEquals('BLP', $settings->getReturnLabelType());
        $this->assertEquals('QR_PDF', $settings->getReturnQrCodeType());
        $this->assertEquals(14, $settings->getReturnLabelExpDays());
        $this->assertEquals(0, $settings->getReturnBookCourier());
        $this->assertEquals(5, $settings->getDropOffPointId());
        $this->assertEquals('B', $settings->getAddressType());
    }

    /**
     * Test multi-package handling and package quantity aggregator.
     *
     * @return void
     */
    public function testMultiPackageHandling(): void
    {
        $pkg1 = new PackageData('PACKAGE', 2, 1.5, 20.0, 30.0, 10.0, false, false);
        $pkg2 = new PackageData('PALLET', 1, 100.0, 80.0, 120.0, 150.0, true, true);

        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            packages: [$pkg1, $pkg2]
        );

        $this->assertCount(2, $settings->getPackages());
        $this->assertEquals(3, $settings->getPackageQuantity()); // 2 + 1
        $this->assertEquals('PACKAGE', $settings->getPackageType()); // First package
        $this->assertEquals(1.5, $settings->getWeight()); // First package weight
    }

    /**
     * Test that explicitly passing empty packages array retains empty packages list.
     *
     * @return void
     */
    public function testEmptyPackagesExplicitlyPassedRetainsEmptyPackages(): void
    {
        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            packages: []
        );

        $this->assertCount(0, $settings->getPackages());
        $this->assertSame([], $settings->toArray()['packages']);
    }

    /**
     * Test array serialization and deserialization via toArray and fromArray with multi-package rows.
     *
     * @return void
     */
    public function testToArrayAndFromArrayWithMultiPackage(): void
    {
        $data = [
            'serviceType' => 'AH',
            'pickupType' => 'COURIER',
            'content' => 'Zamowienie 10001',
            'mpk' => '',
            'comment' => 'Dostawa po 16',
            'reference' => '10001',
            'shipmentDate' => '2026-08-19',
            'shipmentDate' => '2026-08-19',
            'pickupHourFrom' => null,
            'pickupHourTo' => null,
            'packageType' => 'PACKAGE',
            'weight' => 2.0,
            'width' => 15.0,
            'height' => 10.0,
            'length' => 20.0,
            'packageQuantity' => 3,
            'labelType' => 'BLP',
            'isNonStandard' => false,
            'hasEuroReturn' => false,
            'autoCreateReturnShipment' => false,
            'returnLabelType' => null,
            'returnQrCodeType' => null,
            'returnLabelExpDays' => null,
            'returnBookCourier' => null,
            'dropOffPointId' => null,
            'addressType' => 'N',
            'packages' => [
                [
                    'packageType' => 'PACKAGE',
                    'quantity' => 2,
                    'weight' => 2.0,
                    'width' => 15.0,
                    'length' => 20.0,
                    'height' => 10.0,
                    'isNonStandard' => false,
                    'hasEuroReturn' => false,
                ],
                [
                    'packageType' => 'PACKAGE',
                    'quantity' => 1,
                    'weight' => 5.0,
                    'width' => 30.0,
                    'length' => 40.0,
                    'height' => 20.0,
                    'isNonStandard' => true,
                    'hasEuroReturn' => false,
                ],
            ],
        ];

        $settings = ShipmentSettingsData::fromArray($data);
        $this->assertEquals('AH', $settings->getServiceType());
        $this->assertCount(2, $settings->getPackages());
        $this->assertEquals(3, $settings->getPackageQuantity());

        $exported = $settings->toArray();
        $this->assertEquals($data, $exported);
    }

    /**
     * Test that fromArray filters out packages flagged as deleted.
     *
     * @return void
     */
    public function testFromArraySkipsDeletedPackages(): void
    {
        $data = [
            'serviceType' => 'AH',
            'packages' => [
                [
                    'packageType' => 'PACKAGE',
                    'quantity' => 1,
                    'weight' => 2.0,
                    'is_deleted' => '1',
                ],
                [
                    'packageType' => 'PACKAGE',
                    'quantity' => 2,
                    'weight' => 3.0,
                ],
                [
                    'packageType' => 'ENVELOPE',
                    'quantity' => 1,
                    'weight' => 0.5,
                    'delete' => true,
                ],
            ],
        ];

        $settings = ShipmentSettingsData::fromArray($data);
        $this->assertCount(1, $settings->getPackages());
        $this->assertEquals(2, $settings->getPackageQuantity());
        $this->assertEquals(3.0, $settings->getWeight());
    }

    public function testReturnLabelExpDaysDefaultsTo14WhenAutoCreateReturnShipmentIsTrue(): void
    {
        $settings = new ShipmentSettingsData(
            autoCreateReturnShipment: true,
            returnLabelExpDays: null
        );
        $this->assertSame(14, $settings->getReturnLabelExpDays());
        $arr = $settings->toArray();
        $this->assertSame(14, $arr['returnLabelExpDays']);

        $settingsWithZero = new ShipmentSettingsData(
            autoCreateReturnShipment: true,
            returnLabelExpDays: 0
        );
        $this->assertSame(14, $settingsWithZero->getReturnLabelExpDays());
        $arrZero = $settingsWithZero->toArray();
        $this->assertSame(14, $arrZero['returnLabelExpDays']);

        $settingsWithCustom = new ShipmentSettingsData(
            autoCreateReturnShipment: true,
            returnLabelExpDays: 21
        );
        $this->assertSame(21, $settingsWithCustom->getReturnLabelExpDays());

        $fromArray = ShipmentSettingsData::fromArray([
            'autoCreateReturnShipment' => true,
            'returnLabelExpDays' => '0'
        ]);
        $this->assertSame(14, $fromArray->getReturnLabelExpDays());

        $fromArrayDisabled = ShipmentSettingsData::fromArray([
            'autoCreateReturnShipment' => false,
            'returnLabelExpDays' => ''
        ]);
        $this->assertNull($fromArrayDisabled->getReturnLabelExpDays());
    }
}
