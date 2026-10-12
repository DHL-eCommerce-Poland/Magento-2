<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment\Data;

use DhlPoland\Dhl\Model\Shipment\Data\SenderData;
use PHPUnit\Framework\TestCase;

class SenderDataTest extends TestCase
{
    /**
     * Test constructor parameter assignment and getter methods.
     *
     * @return void
     */
    public function testConstructAndGetters(): void
    {
        $sender = new SenderData(
            payer: 'SENDER',
            sapNumber: '1234567',
            name: 'Magazyn Glowny',
            firstName: 'Piotr',
            lastName: 'Zielinski',
            street: 'Magazynowa',
            houseNumber: '1',
            flatNumber: 'A',
            city: 'Poznan',
            postalCode: '60-001',
            country: 'PL',
            phone: '600100200',
            email: 'magazyn@example.com'
        );

        $this->assertEquals('SENDER', $sender->getPayer());
        $this->assertEquals('1234567', $sender->getSapNumber());
        $this->assertEquals('Magazyn Glowny', $sender->getName());
        $this->assertEquals('Piotr', $sender->getFirstName());
        $this->assertEquals('Zielinski', $sender->getLastName());
        $this->assertEquals('Magazynowa', $sender->getStreet());
        $this->assertEquals('1', $sender->getHouseNumber());
        $this->assertEquals('A', $sender->getFlatNumber());
        $this->assertEquals('Poznan', $sender->getCity());
        $this->assertEquals('60-001', $sender->getPostalCode());
        $this->assertEquals('PL', $sender->getCountry());
        $this->assertEquals('600100200', $sender->getPhone());
        $this->assertEquals('magazyn@example.com', $sender->getEmail());
    }

    /**
     * Test default constructor parameter values.
     *
     * @return void
     */
    public function testDefaultValues(): void
    {
        $sender = new SenderData();

        $this->assertEquals('SENDER', $sender->getPayer());
        $this->assertEquals('', $sender->getSapNumber());
        $this->assertEquals('PL', $sender->getCountry());
    }

    /**
     * Test array serialization and deserialization via toArray and fromArray.
     *
     * @return void
     */
    public function testToArrayAndFromArray(): void
    {
        $data = [
            'payer' => 'SENDER',
            'sapNumber' => '7654321',
            'name' => 'Firma Sp. z o.o.',
            'firstName' => 'Adam',
            'lastName' => 'Wisniewski',
            'street' => 'Przemyslowa',
            'houseNumber' => '12',
            'flatNumber' => '',
            'city' => 'Wroclaw',
            'postalCode' => '50-001',
            'country' => 'PL',
            'phone' => '500111222',
            'email' => 'biuro@example.com',
        ];

        $sender = SenderData::fromArray($data);
        $this->assertEquals('7654321', $sender->getSapNumber());
        $this->assertEquals('Firma Sp. z o.o.', $sender->getName());

        $exported = $sender->toArray();
        $this->assertEquals($data, $exported);
    }
}
