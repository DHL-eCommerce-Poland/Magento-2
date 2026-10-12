<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment\Data;

use DhlPoland\Dhl\Model\Shipment\Data\SpecialServicesData;
use PHPUnit\Framework\TestCase;

class SpecialServicesDataTest extends TestCase
{
    /**
     * Test constructor parameter assignment and getter methods.
     *
     * @return void
     */
    public function testConstructAndGetters(): void
    {
        $services = new SpecialServicesData(
            deliveryHours: '1722',
            deliveryOnSaturday: true,
            pickupOnSaturday: true,
            insurance: true,
            insuranceValue: 500.0,
            collectOnDelivery: true,
            collectOnDeliveryValue: 150.0,
            collectOnDeliveryForm: 'BANK_TRANSFER',
            predeliveryInformation: true,
            returnOnDelivery: true,
            returnOnDeliveryReference: 'FV/123/2026',
            proofOfDelivery: true,
            euroReturn: true,
            nonStandard: true
        );

        $this->assertEquals('1722', $services->getDeliveryHours());
        $this->assertTrue($services->isDeliveryOnSaturday());
        $this->assertTrue($services->isPickupOnSaturday());
        $this->assertTrue($services->isInsurance());
        $this->assertEquals(500.0, $services->getInsuranceValue());
        $this->assertTrue($services->isCollectOnDelivery());
        $this->assertEquals(150.0, $services->getCollectOnDeliveryValue());
        $this->assertEquals('BANK_TRANSFER', $services->getCollectOnDeliveryForm());
        $this->assertTrue($services->isPredeliveryInformation());
        $this->assertTrue($services->isReturnOnDelivery());
        $this->assertEquals('FV/123/2026', $services->getReturnOnDeliveryReference());
        $this->assertTrue($services->isProofOfDelivery());
        $this->assertTrue($services->isEuroReturn());
        $this->assertTrue($services->isNonStandard());
    }

    /**
     * Test default constructor parameter values.
     *
     * @return void
     */
    public function testDefaultValues(): void
    {
        $services = new SpecialServicesData();

        $this->assertNull($services->getDeliveryHours());
        $this->assertFalse($services->isDeliveryOnSaturday());
        $this->assertFalse($services->isInsurance());
        $this->assertFalse($services->isCollectOnDelivery());
        $this->assertNull($services->getCollectOnDeliveryValue());
        $this->assertEquals('BANK_TRANSFER', $services->getCollectOnDeliveryForm());
    }

    /**
     * Test array serialization and deserialization via toArray and fromArray.
     *
     * @return void
     */
    public function testToArrayAndFromArray(): void
    {
        $data = [
            'deliveryHours' => null,
            'deliveryEvening' => false,
            'deliveryOnSaturday' => false,
            'pickupOnSaturday' => false,
            'insurance' => true,
            'insuranceValue' => 1000.0,
            'collectOnDelivery' => true,
            'collectOnDeliveryValue' => 299.99,
            'collectOnDeliveryForm' => 'BANK_TRANSFER',
            'predeliveryInformation' => false,
            'returnOnDelivery' => false,
            'returnOnDeliveryReference' => '',
            'proofOfDelivery' => false,
            'euroReturn' => false,
            'nonStandard' => false,
            'ageVer' => false,
        ];

        $services = SpecialServicesData::fromArray($data);
        $this->assertTrue($services->isInsurance());
        $this->assertEquals(1000.0, $services->getInsuranceValue());
        $this->assertTrue($services->isCollectOnDelivery());
        $this->assertEquals(299.99, $services->getCollectOnDeliveryValue());

        $exported = $services->toArray();
        $this->assertEquals(1000.0, $exported['insuranceValue']);
        $this->assertTrue($exported['collectOnDelivery']);
        $this->assertArrayNotHasKey('selfCollect', $exported);
    }

    /**
     * Test fromArray handles standard WebAPI2 fields.
     *
     * @return void
     */
    public function testStandardWebAPI2FieldsInFromArray(): void
    {
        $data = [
            'deliveryEvening' => true,
            'deliveryOnSaturday' => true,
            'pickupOnSaturday' => true,
            'predeliveryInformation' => true,
            'returnOnDelivery' => true,
            'returnOnDeliveryReference' => 'REF-999',
            'proofOfDelivery' => true,
            'ageVer' => true,
        ];

        $services = SpecialServicesData::fromArray($data);
        $this->assertEquals('1722', $services->getDeliveryHours());
        $this->assertTrue($services->isDeliveryOnSaturday());
        $this->assertTrue($services->isPickupOnSaturday());
        $this->assertTrue($services->isPredeliveryInformation());
        $this->assertTrue($services->isReturnOnDelivery());
        $this->assertEquals('REF-999', $services->getReturnOnDeliveryReference());
        $this->assertTrue($services->isProofOfDelivery());
        $this->assertTrue($services->isAgeVer());

        $exported = $services->toArray();
        $this->assertTrue($exported['deliveryEvening']);
        $this->assertTrue($exported['ageVer']);
        $this->assertTrue($exported['deliveryOnSaturday']);
        $this->assertArrayNotHasKey('selfCollect', $exported);
    }
}
