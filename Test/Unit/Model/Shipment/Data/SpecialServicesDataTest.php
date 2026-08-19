<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment\Data;

use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
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
            saturdayDelivery: true,
            saturdayDispatch: true,
            insurance: true,
            insuranceValue: 500.0,
            insuranceCurrency: 'PLN',
            cod: true,
            codAmount: 150.0,
            codCurrency: 'PLN',
            codPaymentMethod: 'CASH',
            pdi: true,
            rod: true,
            rodDocumentText: 'FV/123/2026',
            pod: true,
            euroReturn: true,
            nonStandard: true
        );

        $this->assertEquals('1722', $services->getDeliveryHours());
        $this->assertTrue($services->isSaturdayDelivery());
        $this->assertTrue($services->isSaturdayDispatch());
        $this->assertTrue($services->isInsurance());
        $this->assertEquals(500.0, $services->getInsuranceValue());
        $this->assertEquals('PLN', $services->getInsuranceCurrency());
        $this->assertTrue($services->isCod());
        $this->assertEquals(150.0, $services->getCodAmount());
        $this->assertEquals('PLN', $services->getCodCurrency());
        $this->assertEquals('CASH', $services->getCodPaymentMethod());
        $this->assertTrue($services->isPdi());
        $this->assertTrue($services->isRod());
        $this->assertEquals('FV/123/2026', $services->getRodDocumentText());
        $this->assertTrue($services->isPod());
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
        $this->assertFalse($services->isSaturdayDelivery());
        $this->assertFalse($services->isInsurance());
        $this->assertFalse($services->isCod());
        $this->assertNull($services->getCodAmount());
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
            'saturdayDelivery' => false,
            'saturdayDispatch' => false,
            'insurance' => true,
            'insuranceValue' => 1000.0,
            'insuranceCurrency' => 'PLN',
            'cod' => true,
            'codAmount' => 299.99,
            'codCurrency' => 'PLN',
            'codPaymentMethod' => 'CASH',
            'pdi' => false,
            'rod' => false,
            'rodDocumentText' => '',
            'pod' => false,
            'euroReturn' => false,
            'nonStandard' => false,
            'agever' => false,
            'odb' => false,
        ];

        $services = SpecialServicesData::fromArray($data);
        $this->assertTrue($services->isInsurance());
        $this->assertEquals(1000.0, $services->getInsuranceValue());
        $this->assertTrue($services->isCod());
        $this->assertEquals(299.99, $services->getCodAmount());

        $exported = $services->toArray();
        $this->assertEquals($data, $exported);
    }
}
