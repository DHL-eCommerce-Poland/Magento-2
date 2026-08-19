<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment\Data;

use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use PHPUnit\Framework\TestCase;

class RecipientDataTest extends TestCase
{
    /**
     * Test constructor parameter assignment and getter methods.
     *
     * @return void
     */
    public function testConstructAndGetters(): void
    {
        $recipient = new RecipientData(
            name: 'Acme Corp',
            type: 'B',
            firstName: 'Jan',
            lastName: 'Kowalski',
            contactPerson: 'Jan Kowalski',
            street: 'Kwiatowa',
            houseNumber: '10',
            flatNumber: '2B',
            city: 'Warszawa',
            postalCode: '00-001',
            country: 'PL',
            phone: '123456789',
            email: 'jan@example.com',
            popId: 'POP-123',
            popName: 'DHL POP Point - Warszawa'
        );

        $this->assertEquals('Acme Corp', $recipient->getName());
        $this->assertEquals('B', $recipient->getType());
        $this->assertEquals('Jan', $recipient->getFirstName());
        $this->assertEquals('Kowalski', $recipient->getLastName());
        $this->assertEquals('Jan Kowalski', $recipient->getContactPerson());
        $this->assertEquals('Kwiatowa', $recipient->getStreet());
        $this->assertEquals('10', $recipient->getHouseNumber());
        $this->assertEquals('2B', $recipient->getFlatNumber());
        $this->assertEquals('Warszawa', $recipient->getCity());
        $this->assertEquals('00-001', $recipient->getPostalCode());
        $this->assertEquals('PL', $recipient->getCountry());
        $this->assertEquals('123456789', $recipient->getPhone());
        $this->assertEquals('jan@example.com', $recipient->getEmail());
        $this->assertEquals('POP-123', $recipient->getPopId());
        $this->assertEquals('DHL POP Point - Warszawa', $recipient->getPopName());
    }

    /**
     * Test default constructor parameter values.
     *
     * @return void
     */
    public function testDefaultValues(): void
    {
        $recipient = new RecipientData();

        $this->assertEquals('', $recipient->getName());
        $this->assertEquals('C', $recipient->getType());
        $this->assertEquals('PL', $recipient->getCountry());
        $this->assertNull($recipient->getPopId());
        $this->assertNull($recipient->getPopName());
    }

    /**
     * Test array serialization and deserialization via toArray and fromArray.
     *
     * @return void
     */
    public function testToArrayAndFromArray(): void
    {
        $data = [
            'name' => 'Tech Ltd',
            'type' => 'B',
            'firstName' => 'Anna',
            'lastName' => 'Nowak',
            'contactPerson' => 'Anna Nowak',
            'street' => 'Lipowa',
            'houseNumber' => '5',
            'flatNumber' => '',
            'city' => 'Krakow',
            'postalCode' => '30-001',
            'country' => 'PL',
            'phone' => '987654321',
            'email' => 'anna@example.com',
            'popId' => 'POP-999',
            'popName' => 'DHL POP Krakow',
        ];

        $recipient = RecipientData::fromArray($data);
        $this->assertEquals('Tech Ltd', $recipient->getName());
        $this->assertEquals('POP-999', $recipient->getPopId());

        $exported = $recipient->toArray();
        $this->assertEquals($data, $exported);
    }
}
