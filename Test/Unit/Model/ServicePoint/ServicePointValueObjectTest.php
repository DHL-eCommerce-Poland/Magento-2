<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\ServicePoint;

use Ideo\Dhl\Model\ServicePoint\ServicePointValueObject;
use PHPUnit\Framework\TestCase;

class ServicePointValueObjectTest extends TestCase
{
    public function testGettersReturnConstructedValues(): void
    {
        $point = new ServicePointValueObject(
            'SAP123',
            'Żabka Test',
            'Kwiatowa 5',
            '00-001',
            'Warszawa'
        );

        $this->assertEquals('SAP123', $point->getId());
        $this->assertEquals('Żabka Test', $point->getName());
        $this->assertEquals('Kwiatowa 5', $point->getStreet());
        $this->assertEquals('00-001', $point->getPostcode());
        $this->assertEquals('Warszawa', $point->getCity());
        $this->assertTrue($point->isValid());
    }

    public function testEmptyIdIsInvalid(): void
    {
        $point = new ServicePointValueObject('  ');
        $this->assertFalse($point->isValid());
    }

    public function testToArrayMapping(): void
    {
        $expected = [
            'dhl_service_point_id' => 'SAP999',
            'dhl_service_point_name' => 'Point Name',
            'dhl_service_point_street' => 'Street 1',
            'dhl_service_point_postcode' => '12-345',
            'dhl_service_point_city' => 'City',
            'dhl_is_packstation' => false,
            'dhl_is_postfiliale' => false,
            'dhl_postnummer' => null,
        ];

        $point = new ServicePointValueObject(
            'SAP999',
            'Point Name',
            'Street 1',
            '12-345',
            'City'
        );

        $this->assertEquals($expected, $point->toArray());
    }
}
