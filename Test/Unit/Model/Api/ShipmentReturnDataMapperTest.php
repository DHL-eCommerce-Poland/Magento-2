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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShipmentReturnDataMapperTest extends TestCase
{
    /**
     * @var ShipmentReturnDataMapper
     */
    private $mapper;

    protected function setUp(): void
    {
        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));

        $this->mapper = new ShipmentReturnDataMapper($timezoneStub);
    }

    public function testMapDomesticReturnShipment(): void
    {
        $sender = new SenderData(
            name: 'Firma Klienta',
            firstName: 'Jan',
            lastName: 'Kowalski',
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

        // Check Shipper (customer)
        $this->assertEquals('Firma Klienta', $mapped['ship']['shipper']['address']['name']);
        $this->assertEquals('Jan Kowalski', $mapped['ship']['shipper']['contact']['personName']);
        $this->assertEquals('500600700', $mapped['ship']['shipper']['contact']['phoneNumber']);
        $this->assertEquals('jan@example.com', $mapped['ship']['shipper']['contact']['emailAddress']);
        $this->assertEquals('Jan Kowalski', $mapped['ship']['shipper']['preaviso']['personName']);
        $this->assertEquals('500600700', $mapped['ship']['shipper']['preaviso']['phoneNumber']);
        $this->assertEquals('jan@example.com', $mapped['ship']['shipper']['preaviso']['emailAddress']);
        $this->assertEquals('00001', $mapped['ship']['shipper']['address']['postalCode']);
        $this->assertEquals('Warszawa', $mapped['ship']['shipper']['address']['city']);

        // Check Receiver (merchant Sklep Internetowy)
        $this->assertEquals('Sklep Internetowy Sp. z o.o.', $mapped['ship']['receiver']['address']['name']);
        $this->assertEquals('Sklep Internetowy Sp. z o.o.', $mapped['ship']['receiver']['contact']['personName']);
        $this->assertEquals('123456789', $mapped['ship']['receiver']['contact']['phoneNumber']);
        $this->assertEquals('kontakt@sklep.pl', $mapped['ship']['receiver']['contact']['emailAddress']);
        $this->assertEquals('Sklep Internetowy Sp. z o.o.', $mapped['ship']['receiver']['preaviso']['personName']);
        $this->assertEquals('123456789', $mapped['ship']['receiver']['preaviso']['phoneNumber']);
        $this->assertEquals('kontakt@sklep.pl', $mapped['ship']['receiver']['preaviso']['emailAddress']);
        $this->assertEquals('31000', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Kraków', $mapped['ship']['receiver']['address']['city']);

        // Check primary waybill
        $this->assertEquals('WAYBILL999', $mapped['primaryWaybillNumber']);

        // Check Insurance
        $this->assertNotEmpty($mapped['shipmentInfo']['specialServices']['item']);
        $this->assertEquals('UBEZP', $mapped['shipmentInfo']['specialServices']['item'][0]['serviceType']);
        $this->assertEquals(150.0, $mapped['shipmentInfo']['specialServices']['item'][0]['serviceValue']);
    }

    public function testMapReturnShipmentWithExplicitContactPersons(): void
    {
        $sender = new SenderData(
            name: 'Company Without Person',
            postalCode: '00-001',
            city: 'Warszawa',
            street: 'Marszałkowska',
            houseNumber: '10',
            email: 'company@example.com',
            phone: '500111222'
        );

        $recipient = new RecipientData(
            name: 'Warehouse Corp',
            postalCode: '31-000',
            city: 'Kraków',
            street: 'Floriańska',
            houseNumber: '1',
            contactPerson: 'Marek Magazynier',
            email: 'magazyn@example.com',
            phone: '123444555',
            country: 'PL'
        );

        $pkg = new PackageData('PACKAGE', 1, 1.0, 10.0, 10.0, 10.0, false);
        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            packages: [$pkg]
        );

        $shipmentData = new DhlShipmentData(
            orderId: 2002,
            sender: $sender,
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        // Shipper fallback to company name
        $this->assertEquals('Company Without Person', $mapped['ship']['shipper']['contact']['personName']);
        $this->assertEquals('Company Without Person', $mapped['ship']['shipper']['preaviso']['personName']);
        $this->assertEquals('500111222', $mapped['ship']['shipper']['preaviso']['phoneNumber']);
        $this->assertEquals('company@example.com', $mapped['ship']['shipper']['preaviso']['emailAddress']);

        // Receiver uses explicit contactPerson
        $this->assertEquals('Marek Magazynier', $mapped['ship']['receiver']['contact']['personName']);
        $this->assertEquals('Marek Magazynier', $mapped['ship']['receiver']['preaviso']['personName']);
        $this->assertEquals('123444555', $mapped['ship']['receiver']['preaviso']['phoneNumber']);
        $this->assertEquals('magazyn@example.com', $mapped['ship']['receiver']['preaviso']['emailAddress']);
    }

    public function testSanitizeString(): void
    {
        $dirty = "Test\x00\x07String  ";
        $sanitized = $this->mapper->sanitizeString($dirty);
        $this->assertEquals('TestString', $sanitized);
    }

    /**
     * Test mapping euroReturn flag for PALLET package in return shipment.
     *
     * @return void
     */
    public function testMapEuroReturnForPalletPackage(): void
    {
        $palletPackage = new PackageData(
            packageType: 'PALLET',
            quantity: 1,
            weight: 200.0,
            width: 80.0,
            length: 120.0,
            height: 100.0,
            isNonStandard: false,
            hasEuroReturn: true
        );

        $shipmentData = new DhlShipmentData(
            orderId: 3003,
            sender: new SenderData(),
            recipient: new RecipientData(),
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(packages: [$palletPackage])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertCount(1, $mapped['pieceList']['item']);
        $this->assertEquals('PALLET', $mapped['pieceList']['item'][0]['type']);
        $this->assertTrue($mapped['pieceList']['item'][0]['euroReturn']);
    }

    #[DataProvider('postalCodeDataProvider')]
    public function testSanitizePostalCode(string $inputCode, string $countryCode, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->sanitizePostalCode($inputCode, $countryCode));
    }

    public static function postalCodeDataProvider(): array
    {
        return [
            'PL with hyphen' => ['00-950', 'PL', '00950'],
            'PL without hyphen' => ['00950', 'PL', '00950'],
            'GB alphanumeric' => ['SW1A 1AA', 'GB', 'SW1A 1AA'],
            'DE with hyphen' => ['D-10115', 'DE', 'D-10115'],
            'US zip extended' => ['90210-1234', 'US', '90210-1234'],
        ];
    }
}
