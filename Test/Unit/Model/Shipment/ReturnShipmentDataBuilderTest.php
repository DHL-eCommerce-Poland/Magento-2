<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment;

use DhlPoland\Dhl\Model\Shipment\Data\PackageData;
use DhlPoland\Dhl\Model\Shipment\Data\RecipientData;
use DhlPoland\Dhl\Model\Shipment\Data\SenderData;
use DhlPoland\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use DhlPoland\Dhl\Model\Shipment\Data\SpecialServicesData;
use DhlPoland\Dhl\Model\Shipment\DhlShipmentData;
use DhlPoland\Dhl\Model\Shipment\ReturnShipmentDataBuilder;
use PHPUnit\Framework\TestCase;

class ReturnShipmentDataBuilderTest extends TestCase
{
    /**
     * @var ReturnShipmentDataBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new ReturnShipmentDataBuilder();
    }

    public function testBuildReturnShipmentDataInvertsAddresses(): void
    {
        $sender = new SenderData(
            name: 'Magazyn Sklepu',
            postalCode: '00-999',
            city: 'Warszawa',
            street: 'Magazynowa',
            houseNumber: '5',
            email: 'magazyn@sklep.pl',
            phone: '111222333',
            sapNumber: '654321'
        );

        $recipient = new RecipientData(
            name: 'Piotr Nowak',
            postalCode: '50-100',
            city: 'Wrocław',
            street: 'Rynek',
            houseNumber: '12',
            flatNumber: '3',
            email: 'piotr@example.com',
            phone: '600700800'
        );

        $pkg = new PackageData('PACKAGE', 1, 1.2, 20.0, 15.0, 10.0, false);
        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            content: 'Rzeczy',
            comment: '',
            reference: 'REF001',
            labelType: 'LBLP',
            returnLabelType: 'ZBLP300',
            returnQrCodeType: 'QR4_IMG',
            returnBookCourier: 0,
            returnLabelExpDays: 20,
            packages: [$pkg]
        );
        $services = new SpecialServicesData();

        $original = new DhlShipmentData(
            orderId: 2002,
            sender: $sender,
            recipient: $recipient,
            specialServices: $services,
            shipmentSettings: $settings
        );

        $returnShipment = $this->builder->buildReturnShipmentData($original);

        $this->assertEquals(2002, $returnShipment->getOrderId());

        // Shipper in return = Customer (Piotr Nowak)
        $this->assertEquals('Piotr Nowak', $returnShipment->getSender()->getName());
        $this->assertEquals('50-100', $returnShipment->getSender()->getPostalCode());
        $this->assertEquals('Wrocław', $returnShipment->getSender()->getCity());

        // Recipient in return = Merchant (Magazyn Sklepu)
        $this->assertEquals('Magazyn Sklepu', $returnShipment->getRecipient()->getName());
        $this->assertEquals('00-999', $returnShipment->getRecipient()->getPostalCode());
        $this->assertEquals('Warszawa', $returnShipment->getRecipient()->getCity());

        // Service type for domestic return = ZK
        $this->assertEquals('ZK', $returnShipment->getShipmentSettings()->getServiceType());
        $this->assertEquals('Rzeczy', $returnShipment->getShipmentSettings()->getContent());
        $this->assertSame('ZBLP300', $returnShipment->getShipmentSettings()->getReturnLabelType());
        $this->assertSame('ZBLP300', $returnShipment->getShipmentSettings()->getLabelType());
        $this->assertSame('QR4_IMG', $returnShipment->getShipmentSettings()->getReturnQrCodeType());
        $this->assertEquals(0, $returnShipment->getShipmentSettings()->getReturnBookCourier());
        $this->assertEquals(20, $returnShipment->getShipmentSettings()->getReturnLabelExpDays());
    }

    public function testBuildReturnShipmentDataForInternationalCustomerSetsZcServiceType(): void
    {
        $sender = new SenderData(
            name: 'Magazyn Sklepu',
            postalCode: '00-999',
            city: 'Warszawa',
            country: 'PL'
        );

        $recipient = new RecipientData(
            name: 'Hans Müller',
            postalCode: '10115',
            city: 'Berlin',
            country: 'DE'
        );

        $original = new DhlShipmentData(
            orderId: 3003,
            sender: $sender,
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK')
        );

        $returnShipment = $this->builder->buildReturnShipmentData($original);

        $this->assertEquals('ZC', $returnShipment->getShipmentSettings()->getServiceType());
    }
}
