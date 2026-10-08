<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\ShipmentFullDataMapper;
use Ideo\Dhl\Model\Shipment\Data\CustomsData;
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
            labelType: 'LBLP',
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
        // B2B maps to 'B' in DHL WebAPI2
        $this->assertEquals('B', $mapped['ship']['receiver']['address']['addressType']);
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
        $this->assertSame('LBLP', $mapped['shipmentInfo']['labelType']);
        $this->assertNotEmpty($mapped['shipmentInfo']['specialServices']['item']);
        $items = $mapped['shipmentInfo']['specialServices']['item'];
        $servicesByType = [];
        foreach ($items as $item) {
            $servicesByType[$item['serviceType']] = $item;
        }
        $this->assertArrayHasKey('UBEZP', $servicesByType);
        $this->assertEquals(200.0, $servicesByType['UBEZP']['serviceValue']);
        $this->assertArrayHasKey('COD', $servicesByType);
        $this->assertEquals(150.0, $servicesByType['COD']['serviceValue']);
        $this->assertEquals('BANK_TRANSFER', $servicesByType['COD']['collectOnDeliveryForm']);

        // Check control character sanitization
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
        $this->assertStringNotContainsString("\x07", $mapped['content']);
        $this->assertStringNotContainsString("\x00", $mapped['comment']);
    }

    /**
     * Test servicePointAccountNumber mapping when popId is present and service is point service.
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
        $settings = new ShipmentSettingsData(serviceType: 'SP', packages: [new PackageData()]);

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
     * Test servicePointAccountNumber is null when popId is present but service is courier (AH).
     *
     * @return void
     */
    public function testMapIgnoresServicePointAccountNumberWhenServiceIsCourier(): void
    {
        $recipient = new RecipientData(
            name: 'Jan Kowalski',
            popId: '4529508',
            popName: 'DHL POP ŻABKA'
        );

        $sender = new SenderData();
        $specialServices = new SpecialServicesData();
        $settings = new ShipmentSettingsData(serviceType: 'AH', packages: [new PackageData()]);

        $shipmentData = new DhlShipmentData(
            orderId: 102,
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
    }

    /**
     * Test mapping of ageVer and other special services in the service definition.
     *
     * @return void
     */
    public function testMapIncludesAgeVerAndSpecialServicesWithoutSelfCollect(): void
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
            ageVer: true
        );
        $settings = new ShipmentSettingsData(serviceType: 'AH', packages: [new PackageData()]);

        $shipmentData = new DhlShipmentData(
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('AH', $mapped['shipmentInfo']['serviceType']);
        $this->assertNotEmpty($mapped['shipmentInfo']['specialServices']['item']);
        $items = $mapped['shipmentInfo']['specialServices']['item'];
        $servicesByType = [];
        foreach ($items as $item) {
            $servicesByType[$item['serviceType']] = $item;
        }
        $this->assertArrayHasKey('1722', $servicesByType);
        $this->assertArrayHasKey('SOBOTA', $servicesByType);
        $this->assertArrayHasKey('NAD_SOBOTA', $servicesByType);
        $this->assertArrayHasKey('PDI', $servicesByType);
        $this->assertArrayHasKey('ROD', $servicesByType);
        $this->assertEquals('REF123', $servicesByType['ROD']['textInstruction']);
        $this->assertArrayHasKey('POD', $servicesByType);
        $this->assertArrayHasKey('AGEVER', $servicesByType);
        $this->assertArrayNotHasKey('ODB', $servicesByType);
    }

    /**
     * Test mapping when no special services are enabled.
     *
     * @return void
     */
    public function testMapWithNoSpecialServicesReturnsEmptyArray(): void
    {
        $sender = new SenderData();
        $recipient = new RecipientData(name: 'Test Recipient');
        $specialServices = new SpecialServicesData();
        $settings = new ShipmentSettingsData(serviceType: 'AH', packages: [new PackageData()]);

        $shipmentData = new DhlShipmentData(
            sender: $sender,
            recipient: $recipient,
            specialServices: $specialServices,
            shipmentSettings: $settings
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertIsArray($mapped['shipmentInfo']['specialServices']);
        $this->assertEmpty($mapped['shipmentInfo']['specialServices']);
    }

    /**
     * Test mapping for German international Packstation point.
     *
     * @return void
     */
    public function testMapInternationalGermanPackstationPoint(): void
    {
        $recipient = new RecipientData(
            name: 'Norbert',
            firstName: 'Norbert',
            lastName: 'Test',
            street: 'Home Street',
            houseNumber: '1',
            city: 'Berlin',
            postalCode: '10115',
            country: 'DE',
            popId: '132',
            isPackstation: true,
            postnummer: '12345',
            popStreet: 'Hospitalstr',
            popCity: 'Montabaur',
            popPostcode: '56410'
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
        $this->assertEquals('12345', $mapped['ship']['receiver']['address']['postnummer']);
        $this->assertEquals('56410', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Montabaur', $mapped['ship']['receiver']['address']['city']);
        $this->assertEquals('Hospitalstr', $mapped['ship']['receiver']['address']['street']);
        $this->assertEquals('132', $mapped['ship']['receiver']['address']['houseNumber']);
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
    }

    /**
     * Test mapping for German international Packstation point with address parsed from popName fallback.
     *
     * @return void
     */
    public function testMapInternationalGermanPointParsingFromPopName(): void
    {
        $recipient = new RecipientData(
            name: 'Norbert',
            street: 'Rocky Clarendon Extension Qui commod',
            houseNumber: '215',
            city: 'Incidunt',
            postalCode: '12345',
            country: 'DE',
            popId: '132',
            popName: 'Packstation 132 - Hospitalstr 132, 56410 Montabaur',
            isPackstation: true,
            postnummer: '123456'
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
        $this->assertEquals('C', $mapped['ship']['receiver']['address']['addressType']);
        $this->assertTrue($mapped['ship']['receiver']['address']['isPackstation']);
        $this->assertEquals('123456', $mapped['ship']['receiver']['address']['postnummer']);
        $this->assertEquals('56410', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Montabaur', $mapped['ship']['receiver']['address']['city']);
        $this->assertEquals('Hospitalstr 132', $mapped['ship']['receiver']['address']['street']);
        $this->assertEquals('132', $mapped['ship']['receiver']['address']['houseNumber']);
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
    }

    /**
     * Preserve an international Packstation street number supplied by the point address.
     *
     * @return void
     */
    public function testMapInternationalPackstationPreservesStreetNumberFromPointAddress(): void
    {
        $recipient = new RecipientData(
            name: 'Norbert',
            street: 'Home Street',
            houseNumber: '1',
            city: 'Berlin',
            postalCode: '10115',
            country: 'DE',
            popId: '132',
            isPackstation: true,
            popStreet: 'Hospitalstr 12',
            popCity: 'Montabaur',
            popPostcode: '56410'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 104,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertSame('Hospitalstr 12', $mapped['ship']['receiver']['address']['street']);
        $this->assertSame('132', $mapped['ship']['receiver']['address']['houseNumber']);
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
    }

    /**
     * Test mapping for German international Postfiliale point.
     *
     * @return void
     */
    public function testMapInternationalGermanPostfilialePoint(): void
    {
        $recipient = new RecipientData(
            name: 'Norbert',
            street: 'Home Street',
            houseNumber: '10',
            city: 'Berlin',
            postalCode: '10115',
            country: 'DE',
            popId: '623',
            isPostfiliale: true,
            popStreet: 'Hospitalstr',
            popCity: 'Montabaur',
            popPostcode: '56410'
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
        $this->assertEquals('C', $mapped['ship']['receiver']['address']['addressType']);
        $this->assertTrue($mapped['ship']['receiver']['address']['isPostfiliale']);
        $this->assertArrayNotHasKey('isPackstation', $mapped['ship']['receiver']['address']);
        $this->assertArrayNotHasKey('postnummer', $mapped['ship']['receiver']['address']);
        $this->assertEquals('56410', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Montabaur', $mapped['ship']['receiver']['address']['city']);
        $this->assertEquals('Hospitalstr', $mapped['ship']['receiver']['address']['street']);
        $this->assertEquals('623', $mapped['ship']['receiver']['address']['houseNumber']);
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
    }

    /**
     * Test mapping upgrades point street with house number from popName when popStreet contains only street name.
     *
     * @return void
     */
    public function testMapInternationalGermanPointUpgradesStreetWithHouseNumberFromPopName(): void
    {
        $recipient = new RecipientData(
            name: 'Fields Anderson Associates',
            street: 'Home Street',
            houseNumber: '10',
            city: 'Berlin',
            postalCode: '10115',
            country: 'DE',
            popId: '411',
            popName: 'Lotto-Presse-Tabak - Barkhausenstr. 66, 14612 Falkensee',
            isPostfiliale: true,
            postnummer: '123456',
            popStreet: 'Barkhausenstr.',
            popCity: 'Falkensee',
            popPostcode: '14612'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 105,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('DE', $mapped['ship']['receiver']['address']['country']);
        $this->assertTrue($mapped['ship']['receiver']['address']['isPostfiliale']);
        $this->assertEquals('123456', $mapped['ship']['receiver']['address']['postnummer']);
        $this->assertEquals('14612', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Falkensee', $mapped['ship']['receiver']['address']['city']);
        $this->assertEquals('Lotto-Presse-Tabak - Barkhausenstr. 66', $mapped['ship']['receiver']['address']['street']);
        $this->assertEquals('411', $mapped['ship']['receiver']['address']['houseNumber']);
    }

    /**
     * Test mapping for non-German international Postfiliale point (e.g. AT).
     *
     * @return void
     */
    public function testMapInternationalNonGermanPostfilialePoint(): void
    {
        $recipient = new RecipientData(
            name: 'Johann Straus',
            street: 'Hauptstrasse',
            houseNumber: '1',
            city: 'Wien',
            postalCode: '1010',
            country: 'AT',
            popId: 'AT999',
            isPostfiliale: true,
            popStreet: 'Kärntner Strasse',
            popCity: 'Wien',
            popPostcode: '1010'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 106,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('AT', $mapped['ship']['receiver']['address']['country']);
        $this->assertEquals('C', $mapped['ship']['receiver']['address']['addressType']);
        $this->assertTrue($mapped['ship']['receiver']['address']['isPostfiliale']);
        $this->assertArrayNotHasKey('isPackstation', $mapped['ship']['receiver']['address']);
        $this->assertEquals('1010', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertEquals('Wien', $mapped['ship']['receiver']['address']['city']);
        $this->assertEquals('Kärntner Strasse', $mapped['ship']['receiver']['address']['street']);
        $this->assertEquals('AT999', $mapped['ship']['receiver']['address']['houseNumber']);
    }

    /**
     * Test mapping when point was selected but a Courier service (e.g. AH) is used.

     *
     * @return void
     */
    public function testPointSelectedButCourierServiceUsed(): void
    {
        $recipient = new RecipientData(
            name: 'Anna Muller',
            street: 'Berliner Str.',
            houseNumber: '42',
            city: 'Munich',
            postalCode: '80331',
            country: 'DE',
            popId: '132',
            isPackstation: true,
            postnummer: '12345',
            popStreet: 'Hospitalstr',
            popCity: 'Montabaur',
            popPostcode: '56410'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 104,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'AH', packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('DE', $mapped['ship']['receiver']['address']['country']);
        $this->assertEquals('Berliner Str.', $mapped['ship']['receiver']['address']['street']);
        $this->assertEquals('42', $mapped['ship']['receiver']['address']['houseNumber']);
        $this->assertEquals('Munich', $mapped['ship']['receiver']['address']['city']);
        $this->assertEquals('80331', $mapped['ship']['receiver']['address']['postalCode']);
        $this->assertArrayNotHasKey('isPackstation', $mapped['ship']['receiver']['address']);
        $this->assertArrayNotHasKey('isPostfiliale', $mapped['ship']['receiver']['address']);
        $this->assertArrayNotHasKey('postnummer', $mapped['ship']['receiver']['address']);
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
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
        $this->assertNull($mapped['ship']['servicePointAccountNumber']);
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

        $customs = new CustomsData(
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
        $this->assertFalse($c['customAgreements']['invoiceContent']);
        $this->assertCount(1, $c['customsItem']);
        $this->assertSame('Koszulka', $c['customsItem'][0]['namePl']);
        $this->assertSame('T-Shirt', $c['customsItem'][0]['nameEn']);
        $this->assertSame(2, $c['customsItem'][0]['quantity']);
    }

    /**
     * Ensure empty customs item values stay empty in the DHL request data.
     *
     * @return void
     */
    public function testMapPreservesEmptyCustomsItemFields(): void
    {
        $shipmentData = new DhlShipmentData(
            orderId: 109,
            sender: new SenderData(name: 'Sender Corp'),
            recipient: new RecipientData(name: 'DE Customer', country: 'DE'),
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()]),
            customs: new \Ideo\Dhl\Model\Shipment\Data\CustomsData(
                customsItems: [
                    [
                        'namePl' => '',
                        'nameEn' => '',
                        'quantity' => '',
                        'weight' => '',
                        'value' => '',
                        'tariffCode' => '',
                        'countryOfOrigin' => '',
                    ],
                ]
            )
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertSame(
            [
                'namePl' => '',
                'nameEn' => '',
                'quantity' => '',
                'weight' => '',
                'value' => '',
                'tariffCode' => '',
                'countryOfOrigin' => '',
            ],
            $mapped['customs']['customsItem'][0]
        );
    }

    /**
     * Ensure comma decimal values are normalized before building the DHL request data.
     *
     * @return void
     */
    public function testMapConvertsCommaDecimalCustomsItemValues(): void
    {
        $shipmentData = new DhlShipmentData(
            orderId: 110,
            sender: new SenderData(name: 'Sender Corp'),
            recipient: new RecipientData(name: 'DE Customer', country: 'DE'),
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()]),
            customs: new \Ideo\Dhl\Model\Shipment\Data\CustomsData(
                customsItems: [
                    [
                        'quantity' => '2',
                        'weight' => '0,75',
                        'value' => '10,50',
                    ],
                ]
            )
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertSame(2, $mapped['customs']['customsItem'][0]['quantity']);
        $this->assertSame(0.75, $mapped['customs']['customsItem'][0]['weight']);
        $this->assertSame(10.5, $mapped['customs']['customsItem'][0]['value']);
    }

    public function testMapToShipmentFullDataWithIndividualCustomsBase64Invoice(): void
    {
        $sender = new SenderData(name: 'Sender Corp');
        $recipient = new RecipientData(name: 'UK Customer Ltd', country: 'GB');

        $base64Content = base64_encode('%PDF-1.4 Fake PDF Content');

        $customs = new \Ideo\Dhl\Model\Shipment\Data\CustomsData(
            customsType: 'I',
            invoiceContent: true,
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

    /**
     * Verify the individual customs agreement follows the selected checkbox value.
     *
     * @return void
     */
    public function testInvoiceContentAgreementUsesCheckboxValueForIndividualClearance(): void
    {
        $shipmentData = new DhlShipmentData(
            orderId: 107,
            sender: new SenderData(name: 'Sender Corp'),
            recipient: new RecipientData(name: 'UK Customer Ltd', country: 'GB'),
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()]),
            customs: new CustomsData(customsType: 'I', invoiceContent: false)
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertFalse($mapped['customs']['customAgreements']['invoiceContent']);
    }

    /**
     * Verify non-individual customs types never send the invoice content agreement.
     *
     * @param string $customsType
     *
     * @return void
     */
    #[DataProvider('nonIndividualCustomsTypesDataProvider')]
    public function testInvoiceContentAgreementIsFalseForNonIndividualClearance(string $customsType): void
    {
        $shipmentData = new DhlShipmentData(
            orderId: 108,
            sender: new SenderData(name: 'Sender Corp'),
            recipient: new RecipientData(name: 'UK Customer Ltd', country: 'GB'),
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(serviceType: 'EK', packages: [new PackageData()]),
            customs: new CustomsData(customsType: $customsType, invoiceContent: true)
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertFalse($mapped['customs']['customAgreements']['invoiceContent']);
    }

    /**
     * Provide customs types that do not use the invoice content agreement.
     *
     * @return array
     */
    public static function nonIndividualCustomsTypesDataProvider(): array
    {
        return [
            'simplified clearance' => ['U'],
            'short declaration' => ['S'],
        ];
    }

    #[DataProvider('postalCodeDataProvider')]
    public function testSanitizePostalCode(string $inputCode, string $countryCode, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->sanitizePostalCode($inputCode, $countryCode));
    }

    public function testMapRemovesInternalSpaceFromCzechSenderAndRecipientPostalCodes(): void
    {
        $shipmentData = new DhlShipmentData(
            orderId: 108,
            sender: new SenderData(postalCode: '100 00', country: 'CZ'),
            recipient: new RecipientData(postalCode: '110 00', country: 'CZ'),
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertSame('10000', $mapped['ship']['shipper']['address']['postalCode']);
        $this->assertSame('11000', $mapped['ship']['receiver']['address']['postalCode']);
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

    /**
     * Test mapping uses recipient first and last name when provided over stale contactPerson.
     *
     * @return void
     */
    public function testMapUsesRecipientFirstAndLastNameWhenProvided(): void
    {
        $recipient = new RecipientData(
            name: '',
            type: 'C',
            firstName: 'Piotr',
            lastName: 'Nowak',
            contactPerson: 'Jan Kowalski',
            street: 'Warszawska',
            houseNumber: '1',
            city: 'Warszawa',
            postalCode: '00-001',
            country: 'PL'
        );

        $shipmentData = new DhlShipmentData(
            orderId: 201,
            sender: new SenderData(),
            recipient: $recipient,
            specialServices: new SpecialServicesData(),
            shipmentSettings: new ShipmentSettingsData(packages: [new PackageData()])
        );

        $mapped = $this->mapper->map($shipmentData);

        $this->assertEquals('Piotr Nowak', $mapped['ship']['receiver']['contact']['personName']);
        $this->assertEquals('Piotr Nowak', $mapped['ship']['receiver']['address']['name']);
    }

    /**
     * Test mapping euroReturn flag for PALLET package with euro return enabled.
     *
     * @return void
     */
    public function testMapEuroReturnForPalletPackage(): void
    {
        $palletPackage = new PackageData(
            packageType: 'PALLET',
            quantity: 1,
            weight: 150.0,
            width: 80.0,
            length: 120.0,
            height: 100.0,
            isNonStandard: false,
            hasEuroReturn: true
        );

        $shipmentData = new DhlShipmentData(
            orderId: 301,
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

    public static function postalCodeDataProvider(): array
    {
        return [
            'PL with hyphen' => ['00-950', 'PL', '00950'],
            'PL without hyphen' => ['00950', 'PL', '00950'],
            'CZ with internal space' => ['100 00', 'CZ', '10000'],
            'GB alphanumeric with space' => ['SW1A 1AA', 'GB', 'SW1A1AA'],
            'DE with hyphen' => ['D-10115', 'DE', 'D-10115'],
            'US zip extended' => ['90210-1234', 'US', '90210-1234'],
        ];
    }
}
