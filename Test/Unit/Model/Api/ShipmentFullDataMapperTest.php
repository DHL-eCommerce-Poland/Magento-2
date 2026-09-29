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
            name: 'Moja Firma Sp. z o.o.',
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
            deliveryOnSaturday: false,
            pickupOnSaturday: false,
            insurance: true,
            insuranceValue: 200.0,
            collectOnDelivery: true,
            collectOnDeliveryValue: 150.0,
            collectOnDeliveryForm: 'BANK_TRANSFER'
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

        // Check dropOffType constant inside shipmentInfo
        $this->assertEquals('REGULAR_PICKUP', $mapped['shipmentInfo']['dropOffType']);

        // Check pickup hours inside shipmentTime
        $this->assertEquals('10:00', $mapped['shipmentInfo']['shipmentTime']['shipmentStartHour']);
        $this->assertEquals('14:00', $mapped['shipmentInfo']['shipmentTime']['shipmentEndHour']);

        // Check shipper inside ship
        $this->assertEquals('Moja Firma Sp. z o.o.', $mapped['ship']['shipper']['address']['name']);
        $this->assertEquals('Jan Kowalski', $mapped['ship']['shipper']['contact']['personName']);
        $this->assertEquals('00001', $mapped['ship']['shipper']['address']['postalCode']); // Hyphen stripped
        $this->assertEquals('Warszawa', $mapped['ship']['shipper']['address']['city']);
        $this->assertEquals('2', $mapped['ship']['shipper']['address']['apartmentNumber']);

        // Check receiver inside ship
        $this->assertEquals('B', $mapped['ship']['receiver']['address']['addressType']); // B2B maps to 'B' in DHL WebAPI2
        $this->assertEquals('Firma ABC', $mapped['ship']['receiver']['address']['name']);
        $this->assertEquals('30002', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('PL', $mapped['ship']['receiver']['address']['country']);

        // Check pieceList
        $this->assertCount(1, $mapped['pieceList']['item']);
        $this->assertEquals('PACKAGE', $mapped['pieceList']['item'][0]['type']);
        $this->assertEquals(5.5, $mapped['pieceList']['item'][0]['weight']);

        // Check payment & service inside shipmentInfo
        $this->assertEquals('SHIPPER', $mapped['shipmentInfo']['billing']['shippingPaymentType']);
        $this->assertEquals('1234567', $mapped['shipmentInfo']['billing']['billingAccountNumber']);
        $this->assertEquals('MPK123', $mapped['shipmentInfo']['billing']['costsCenter']);
        $this->assertEquals('AH', $mapped['shipmentInfo']['serviceType']);
        $this->assertTrue($mapped['shipmentInfo']['specialServices']['collectOnDelivery']);
        $this->assertEquals(150.0, $mapped['shipmentInfo']['specialServices']['collectOnDeliveryValue']);

        // Check control character sanitization
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
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

        $this->assertEquals('4529508', $mapped['ship']['servicePointAccountNumber']);
        $this->assertEquals('C', $mapped['ship']['receiver']['address']['addressType']);
    }

    /**
     * Test mapping of ageVer, selfCollect, and special services in service definition.
     *
     * @return void
     */
    public function testMapIncludesAgeVerAndSelfCollectAndSpecialServicesInServiceDef(): void
    {
        $sender = new SenderData();
        $recipient = new RecipientData(name: 'Test Recipient');
        $specialServices = new SpecialServicesData(
            deliveryHours: '1722',
            deliveryOnSaturday: true,
            pickupOnSaturday: true,
            predeliveryInformation: true,
            returnOnDelivery: true,
            returnOnDeliveryReference: 'REF123',
            proofOfDelivery: true,
            ageVer: true,
            selfCollect: true
        );
        $settings = new ShipmentSettingsData(serviceType: 'AH', packages: [new PackageData()]);

        $shipmentData = new DhlShipmentData(
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        $service = $mapped['shipmentInfo']['specialServices'];
        $this->assertEquals('AH', $mapped['shipmentInfo']['serviceType']);
        $this->assertTrue($service['deliveryEvening']);
        $this->assertTrue($service['deliveryOnSaturday']);
        $this->assertTrue($service['pickupOnSaturday']);
        $this->assertTrue($service['predeliveryInformation']);
        $this->assertTrue($service['returnOnDelivery']);
        $this->assertEquals('REF123', $service['returnOnDeliveryReference']);
        $this->assertTrue($service['proofOfDelivery']);
        $this->assertTrue($service['ageVer']);
        $this->assertTrue($service['selfCollect']);
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
        $settings = new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()]);

        $shipmentData = new DhlShipmentData(
            orderId: 102,
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('DE', $mapped['ship']['receiver']['address']['country']);
        $this->assertEquals('C', $mapped['ship']['receiver']['address']['addressType']);
        $this->assertTrue($mapped['ship']['receiver']['address']['isPackstation']);
        $this->assertArrayNotHasKey('isPostfiliale', $mapped['ship']['receiver']['address']);
        $this->assertEquals('987654321', $mapped['ship']['receiver']['address']['postnummer']);
        $this->assertEquals('49393', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Lohne', $mapped['ship']['receiver']['address']['city']);
        $this->assertArrayNotHasKey('customs', $mapped);
    }

    /**
     * Test mapping for German international Postfiliale point.
     *
     * @return void
     */
    public function testMapInternationalGermanPostfilialePoint(): void
    {
        $recipient = new RecipientData(
            name: 'Hans Schmidt',
            street: 'Hauptstr.',
            houseNumber: '10',
            city: 'Berlin',
            postalCode: '10115',
            country: 'DE',
            popId: 'FILIALE-501',
            isPostfiliale: true,
            postnummer: '123456789'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 103,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('DE', $mapped['ship']['receiver']['address']['country']);
        $this->assertTrue($mapped['ship']['receiver']['address']['isPostfiliale']);
        $this->assertArrayNotHasKey('isPackstation', $mapped['ship']['receiver']['address']);
        $this->assertEquals('123456789', $mapped['ship']['receiver']['address']['postnummer']);
    }

    /**
     * Test mapping for German standard home address delivery.
     *
     * @return void
     */
    public function testMapInternationalGermanHomeAddress(): void
    {
        $recipient = new RecipientData(
            name: 'Anna Muller',
            street: 'Berliner Str.',
            houseNumber: '42',
            city: 'Munich',
            postalCode: '80331',
            country: 'DE'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 104,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('DE', $mapped['ship']['receiver']['address']['country']);
        $this->assertArrayNotHasKey('isPackstation', $mapped['ship']['receiver']['address']);
        $this->assertArrayNotHasKey('isPostfiliale', $mapped['ship']['receiver']['address']);
        $this->assertArrayNotHasKey('postnummer', $mapped['ship']['receiver']['address']);
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
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()]),
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
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()]),
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

        $this->assertSame('13/435', $mapped['ship']['shipper']['address']['houseNumber']);
        $this->assertSame('12A/4', $mapped['ship']['receiver']['address']['houseNumber']);
    }

    #[DataProvider('shipperNameAndContactPersonProvider')]
    public function testMapShipperNameAndContactPersonVariants(
        string $companyName,
        string $firstName,
        string $lastName,
        string $expectedName,
        string $expectedContactPerson
    ): void {
        $sender = new SenderData(
            name: $companyName,
            firstName: $firstName,
            lastName: $lastName
        );

        $shipmentData = new DhlShipmentData(
            orderId: 200,
            sender: $sender,
            recipient: new RecipientData(name: 'Recipient'),
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertSame($expectedName, $mapped['ship']['shipper']['address']['name']);
        $this->assertSame($expectedContactPerson, $mapped['ship']['shipper']['contact']['personName']);
    }

    public static function shipperNameAndContactPersonProvider(): array
    {
        return [
            'company and person specified' => [
                'Firma Testowa',
                'Jan',
                'Kowalski',
                'Firma Testowa',
                'Jan Kowalski',
            ],
            'only person specified' => [
                '',
                'Jan',
                'Kowalski',
                'Jan Kowalski',
                'Jan Kowalski',
            ],
            'only company specified' => [
                'Firma Testowa',
                '',
                '',
                'Firma Testowa',
                '',
            ],
        ];
    }

    /**
     * Test mapping for domestic Polish shipment does not contain isPostfiliale or postnummer tags.
     *
     * @return void
     */
    public function testDomesticShipmentDoesNotContainIsPostfilialeOrPostnummer(): void
    {
        $recipient = new RecipientData(
            name: 'Martena Shelton',
            street: 'Warszawska',
            houseNumber: '29/44',
            city: 'Vel volupt',
            postalCode: '38500',
            country: 'PL',
            isPostfiliale: true,
            postnummer: '12345678'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 200,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('PL', $mapped['ship']['receiver']['address']['country']);
        $this->assertArrayNotHasKey('isPostfiliale', $mapped['ship']['receiver']['address']);
        $this->assertArrayNotHasKey('isPackstation', $mapped['ship']['receiver']['address']);
        $this->assertArrayNotHasKey('postnummer', $mapped['ship']['receiver']['address']);
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
