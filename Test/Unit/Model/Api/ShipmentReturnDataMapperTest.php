<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\ShipmentReturnDataMapper;
use Ideo\Dhl\Model\Shipment\Data\PackageData;
use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use PHPUnit\Framework\TestCase;

class ShipmentReturnDataMapperTest extends TestCase
{
    private ShipmentReturnDataMapper $mapper;

    protected function setUp(): void
    {
        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));

        $this->mapper = new ShipmentReturnDataMapper($timezoneStub);
    }

    public function testMapDomesticReturnShipment(): void
    {
        $sender = new SenderData(
            name: 'Jan Kowalski',
            postalCode: '00-001',
            city: 'Warszawa',
            street: 'Marszałkowska',
            houseNumber: '10',
            flatNumber: '2',
            email: 'jan@example.com',
            phone: '500600700',
            sapNumber: '123456'
        );

        $recipient = new RecipientData(
            name: 'Sklep Internetowy Sp. z o.o.',
            postalCode: '31-000',
            city: 'Kraków',
            street: 'Floriańska',
            houseNumber: '1',
            flatNumber: '',
            email: 'kontakt@sklep.pl',
            phone: '123456789',
            type: 'B',
            country: 'PL'
        );

        $pkg = new PackageData('PACKAGE', 1, 2.5, 30.0, 20.0, 10.0, false);
        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            content: 'Zawartość przesyłki',
            mpk: 'MPK99',
            comment: 'Komentarz',
            reference: 'REF123',
            labelType: 'BLP',
            packages: [$pkg]
        );
        $services = new SpecialServicesData(insurance: true, insuranceValue: 150.0);

        $shipmentData = new DhlShipmentData(
            orderId: 1001,
            sender: $sender,
            recipient: $recipient,
            specialServices: $services,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData, 14, 'WAYBILL999');

        $this->assertEquals('ZK', $mapped['shipmentInfo']['serviceType']);
        $this->assertEquals('RECEIVER', $mapped['shipmentInfo']['billing']['shippingPaymentType']);
        $this->assertEquals(123456, $mapped['shipmentInfo']['billing']['billingAccountNumber']);
        $this->assertEquals('BANK_TRANSFER', $mapped['shipmentInfo']['billing']['paymentType']);
        $this->assertEquals('BLP', $mapped['shipmentInfo']['labelType']);

        // Check Shipper (customer Jan Kowalski)
        $this->assertEquals('Jan Kowalski', $mapped['ship']['shipper']['address']['name']);
        $this->assertEquals('00001', $mapped['ship']['shipper']['address']['postalCode']);
        $this->assertEquals('Warszawa', $mapped['ship']['shipper']['address']['city']);

        // Check Receiver (merchant Sklep Internetowy)
        $this->assertEquals('Sklep Internetowy Sp. z o.o.', $mapped['ship']['receiver']['address']['name']);
        $this->assertEquals('31000', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Kraków', $mapped['ship']['receiver']['address']['city']);

        // Check primary waybill
        $this->assertEquals('WAYBILL999', $mapped['primaryWaybillNumber']);

        // Check Insurance
        $this->assertNotEmpty($mapped['shipmentInfo']['specialServices']['item']);
        $this->assertEquals('UBEZP', $mapped['shipmentInfo']['specialServices']['item'][0]['serviceType']);
        $this->assertEquals(150.0, $mapped['shipmentInfo']['specialServices']['item'][0]['serviceValue']);
    }

    public function testSanitizeAndTruncateString(): void
    {
        $dirty = "Test\x00\x07String  ";
        $sanitized = $this->mapper->sanitizeString($dirty);
        $this->assertEquals('TestString', $sanitized);

        $truncated = $this->mapper->truncateString('Bardzo długa zawartość paczki zwrotnej', 10);
        $this->assertEquals('Bardzo dłu', $truncated);
    }
}
