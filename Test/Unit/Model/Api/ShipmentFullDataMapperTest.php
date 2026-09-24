<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\ShipmentFullDataMapper;
use Ideo\Dhl\Model\Shipment\Data\PackageData;
use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShipmentFullDataMapperTest extends TestCase
{
    /**
     * @var ShipmentFullDataMapper
     */
    private $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ShipmentFullDataMapper();
    }

    /**
     * Test mapping DhlShipmentData to DHL WebAPI2 ShipmentFullData structure.
     *
     * @return void
     */
    public function testMapToShipmentFullData(): void
    {
        $sender = new SenderData(
            payer: 'SENDER',
            sapNumber: '1234567',
            name: 'Jan Kowalski',
            firstName: 'Jan',
            lastName: 'Kowalski',
            street: 'Prosta',
            houseNumber: '10',
            flatNumber: '2',
            city: 'Warszawa',
            postalCode: '00-001',
            country: 'PL',
            phone: '500100100',
            email: 'jan@example.com'
        );

        $recipient = new RecipientData(
            name: 'Firma ABC',
            type: 'B',
            firstName: 'Anna',
            lastName: 'Nowak',
            contactPerson: 'Anna Nowak',
            street: 'Długa',
            houseNumber: '15',
            flatNumber: '',
            city: 'Kraków',
            postalCode: '30-002',
            country: 'PL',
            phone: '600200300',
            email: 'anna@example.com'
        );

        $specialServices = new SpecialServicesData(
            deliveryHours: null,
            saturdayDelivery: false,
            saturdayDispatch: false,
            insurance: true,
            insuranceValue: 200.0,
            insuranceCurrency: 'PLN',
            cod: true,
            codAmount: 150.0,
            codCurrency: 'PLN',
            codPaymentMethod: 'CASH'
        );

        $packages = [
            new PackageData(
                packageType: 'PACKAGE',
                quantity: 1,
                weight: 5.5,
                width: 20,
                length: 40,
                height: 30,
                isNonStandard: false
            )
        ];

        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            pickupType: 'COURIER',
            content: "Towary handlowe\x07 ze sklepem", // Contains control character \x07
            mpk: 'MPK123',
            comment: "Bardzo długa uwaga z niewidocznym znakiem \x00 przekraczająca limit znaków,"
                . " która musi zostać obcięta do 100 znaków",
            reference: 'ORDER #10000123',
            shipmentDate: '2026-08-25',
            pickupHourFrom: '10:00',
            pickupHourTo: '14:00',
            packages: $packages
        );

        $shipmentData = new DhlShipmentData(
            orderId: 100,
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        // Check pickup hours
        $this->assertEquals('10:00', $mapped['shipmentStartHour']);
        $this->assertEquals('14:00', $mapped['shipmentEndHour']);

        // Check shipper
        $this->assertEquals('Jan Kowalski', $mapped['shipper']['name']);
        $this->assertEquals('00001', $mapped['shipper']['postalCode']); // Hyphen stripped
        $this->assertEquals('Warszawa', $mapped['shipper']['city']);
        $this->assertEquals('2', $mapped['shipper']['apartmentNumber']);

        // Check receiver
        $this->assertEquals('B', $mapped['receiver']['addressType']); // B2B maps to 'B' in DHL WebAPI2
        $this->assertEquals('Firma ABC', $mapped['receiver']['name']);
        $this->assertEquals('30002', $mapped['receiver']['postalCode']);
        $this->assertEquals('PL', $mapped['receiver']['country']);

        // Check pieceList
        $this->assertCount(1, $mapped['pieceList']['item']);
        $this->assertEquals('PACKAGE', $mapped['pieceList']['item'][0]['type']);
        $this->assertEquals(5.5, $mapped['pieceList']['item'][0]['weight']);

        // Check payment & service
        $this->assertEquals('SHIPPER', $mapped['payment']['payerType']);
        $this->assertEquals('1234567', $mapped['payment']['accountNumber']);
        $this->assertEquals('MPK123', $mapped['payment']['costsCenter']);
        $this->assertEquals('AH', $mapped['service']['product']);
        $this->assertTrue($mapped['service']['collectOnDelivery']);
        $this->assertEquals(150.0, $mapped['service']['collectOnDeliveryValue']);

        // Check control character sanitization
        $this->assertFalse($mapped['skipRestrictionCheck']);
        $this->assertNull($mapped['servicePointAccountNumber']);
        $this->assertStringNotContainsString("\x07", $mapped['content']);
        $this->assertStringNotContainsString("\x00", $mapped['comment']);
    }

    /**
     * Test servicePointAccountNumber mapping when popId is present.
     *
     * @return void
     */
    public function testMapIncludesServicePointAccountNumberWhenPopIdPresent(): void
    {
        $recipient = new RecipientData(
            name: 'Jan Kowalski',
            popId: '4529508',
            popName: 'DHL POP ŻABKA'
        );

        $sender = new SenderData();
        $specialServices = new SpecialServicesData();
        $settings = new ShipmentSettingsData(packages: [new PackageData()]);

        $shipmentData = new DhlShipmentData(
            orderId: 101,
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);
        $this->assertEquals('4529508', $mapped['servicePointAccountNumber']);
        $this->assertEquals('C', $mapped['receiver']['addressType']);
    }

    /**
     * Test mapping for German international Packstation point.
     *
     * @return void
     */
    public function testMapInternationalGermanPackstationPoint(): void
    {
        $recipient = new RecipientData(
            name: 'Max Mustermann',
            firstName: 'Max',
            lastName: 'Mustermann',
            street: 'Keetstr.',
            houseNumber: '19',
            city: 'Lohne',
            postalCode: '49393',
            country: 'DE',
            popId: 'PACK-123',
            isPackstation: true,
            postnummer: '987654321'
        );

        $sender = new SenderData();
        $specialServices = new SpecialServicesData();
        $settings = new ShipmentSettingsData(serviceType: 'EKS', packages: [new PackageData()]);

        $shipmentData = new DhlShipmentData(
            orderId: 102,
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('DE', $mapped['receiver']['country']);
        $this->assertEquals('C', $mapped['receiver']['addressType']);
        $this->assertTrue($mapped['receiver']['isPackstation']);
        $this->assertEquals('987654321', $mapped['receiver']['postnummer']);
        $this->assertEquals('49393', $mapped['receiver']['postalCode']);
        $this->assertEquals('Lohne', $mapped['receiver']['city']);
        $this->assertArrayNotHasKey('customs', $mapped);
    }

    public function testMapToShipmentFullDataWithCustomsWhenCountryIsNotPl(): void
    {
        $sender = new SenderData(
            payer: 'SENDER',
            sapNumber: '1234567',
            name: 'Sender Corp'
        );

        $recipient = new RecipientData(
            name: 'UK Customer Ltd',
            street: 'High Street',
            houseNumber: '10',
            city: 'London',
            postalCode: 'SW1A 1AA',
            country: 'GB'
        );

        $customs = new \Ideo\Dhl\Model\Shipment\Data\CustomsData(
            selectedCustomsTemplateId: 5,
            customsType: 'U',
            categoryOfItem: '11',
            currency: 'GBP',
            countryOfOrigin: 'PL',
            defaultTariffCode: '61091000',
            costsOfShipment: 19.99,
            grossWeight: 1.5,
            firstName: 'John',
            secondaryName: 'Smith',
            nipNr: '5218487281',
            eoriNr: 'PL52184872810000',
            vatRegistrationNumber: 'PL5218487281',
            eoriNrReceiver: 'GB123456789000',
            vatRegistrationNumberReceiver: 'GB123456789',
            additionalInfo: 'Customs note',
            notExceedValue: true,
            notProhibitedGoods: true,
            notRestrictedGoods: true,
            customsItems: [
                [
                    'namePl' => 'Koszulka',
                    'nameEn' => 'T-Shirt',
                    'quantity' => 2,
                    'weight' => 0.75,
                    'value' => 30.00,
                    'tariffCode' => '61091000',
                    'countryOfOrigin' => 'PL',
                ],
            ]
        );

        $shipmentData = new DhlShipmentData(
            orderId: 105,
            sender: $sender,
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EKS', packages: [new PackageData()]),
            itemsQty: [],
            customs: $customs
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertArrayHasKey('customs', $mapped);
        $c = $mapped['customs'];
        $this->assertSame('U', $c['customsType']);
        $this->assertSame(20, $c['costsOfShipment']); // (int)round(19.99)
        $this->assertSame('GBP', $c['currency']);
        $this->assertSame('11', $c['categoryOfItem']);
        $this->assertSame('PL', $c['countryOfOrigin']);
        $this->assertSame(1.5, $c['grossWeight']);
        $this->assertSame('John', $c['firstName']);
        $this->assertSame('Smith', $c['secondaryName']);
        $this->assertSame('5218487281', $c['nipNr']);
        $this->assertSame('PL52184872810000', $c['eoriNr']);
        $this->assertSame('PL5218487281', $c['vatRegistrationNumber']);
        $this->assertSame('GB123456789000', $c['eoriNrReceiver']);
        $this->assertSame('GB123456789', $c['vatRegistrationNumberReceiver']);
        $this->assertSame('Customs note', $c['additionalInfo']);
        $this->assertTrue($c['customAgreements']['notExceedValue']);
        $this->assertTrue($c['customAgreements']['notProhibitedGoods']);
        $this->assertTrue($c['customAgreements']['notRestrictedGoods']);
        $this->assertCount(1, $c['customsItem']);
        $this->assertSame('Koszulka', $c['customsItem'][0]['namePl']);
        $this->assertSame('T-Shirt', $c['customsItem'][0]['nameEn']);
        $this->assertSame(2, $c['customsItem'][0]['quantity']);
    }

    public function testMapToShipmentFullDataWithIndividualCustomsBase64Invoice(): void
    {
        $sender = new SenderData(name: 'Sender Corp');
        $recipient = new RecipientData(name: 'UK Customer Ltd', country: 'GB');

        $base64Content = base64_encode('%PDF-1.4 Fake PDF Content');

        $customs = new \Ideo\Dhl\Model\Shipment\Data\CustomsData(
            customsType: 'I',
            invoiceNr: 'INV-2026-001',
            invoiceDate: '2026-09-23',
            invoiceFile: 'data:application/pdf;base64,' . $base64Content,
            customsItems: [
                ['namePl' => 'Koszulka', 'nameEn' => 'T-Shirt', 'quantity' => 1, 'weight' => 0.5, 'value' => 10.0]
            ]
        );

        $shipmentData = new DhlShipmentData(
            orderId: 106,
            sender: $sender,
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EKS', packages: [new PackageData()]),
            customs: $customs
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertArrayHasKey('customs', $mapped);
        $c = $mapped['customs'];
        $this->assertSame('I', $c['customsType']);
        $this->assertSame('INV-2026-001', $c['invoiceNr']);
        $this->assertSame('2026-09-23', $c['invoiceDate']);
        $this->assertSame($base64Content, $c['invoice']);
        $this->assertTrue($c['customAgreements']['invoiceContent']);
    }

    #[DataProvider('postalCodeDataProvider')]
    public function testSanitizePostalCode(string $inputCode, string $countryCode, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->sanitizePostalCode($inputCode, $countryCode));
    }

    public function testMapPreservesHouseNumberWithSlash(): void
    {
        $sender = new SenderData(
            houseNumber: '13/435'
        );
        $recipient = new RecipientData(
            houseNumber: '12A/4'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 107,
            sender: $sender,
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertSame('13/435', $mapped['shipper']['houseNumber']);
        $this->assertSame('12A/4', $mapped['receiver']['houseNumber']);
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
