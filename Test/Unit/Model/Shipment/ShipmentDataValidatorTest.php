<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment;

use DateTime;
use DhlPoland\Dhl\Model\Shipment\Data\PackageData;
use DhlPoland\Dhl\Model\Shipment\Data\RecipientData;
use DhlPoland\Dhl\Model\Shipment\Data\SenderData;
use DhlPoland\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use DhlPoland\Dhl\Model\Shipment\DhlShipmentData;
use DhlPoland\Dhl\Model\Shipment\ShipmentDataValidator;
use Magento\Framework\Exception\LocalizedException;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ShipmentDataValidatorTest extends TestCase
{
    /**
     * @var ShipmentDataValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $timezoneMock = $this->createMock(TimezoneInterface::class);
        $timezoneMock->method('scopeDate')
            ->willReturn(new DateTime('2026-09-02'));

        $this->validator = new ShipmentDataValidator($timezoneMock);
    }

    /**
     * Test valid shipment data passes validation without exception.
     *
     * @return void
     */
    public function testValidateValidShipmentData(): void
    {
        $sender = new SenderData(
            sapNumber: '1234567',
            name: 'Magazyn Główny',
            street: 'Przemysłowa',
            houseNumber: '10',
            city: 'Warszawa',
            postalCode: '00-001'
        );

        $recipient = new RecipientData(
            firstName: 'Jan',
            lastName: 'Kowalski',
            street: 'Marszałkowska',
            houseNumber: '5',
            city: 'Kraków',
            postalCode: '30-001'
        );

        $package = new PackageData(
            packageType: 'PACKAGE',
            quantity: 1,
            weight: 2.0,
            width: 20.0,
            length: 30.0,
            height: 15.0
        );

        $settings = new ShipmentSettingsData(
            content: 'Sprzęt elektroniczny',
            shipmentDate: date('Y-m-d'),
            packages: [$package]
        );

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings
        );

        $this->expectNotToPerformAssertions();
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validation passes when sender has only first and last name without company name.
     *
     * @return void
     */
    public function testValidateValidSenderWithOnlyFirstAndLastName(): void
    {
        $sender = new SenderData(
            firstName: 'Jan',
            lastName: 'Kowalski',
            street: 'Przemysłowa',
            houseNumber: '10',
            city: 'Warszawa',
            postalCode: '00-001'
        );

        $recipient = new RecipientData(
            firstName: 'Anna',
            lastName: 'Nowak',
            street: 'Marszałkowska',
            houseNumber: '5',
            city: 'Kraków',
            postalCode: '30-001'
        );

        $package = new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 2.0);
        $settings = new ShipmentSettingsData(content: 'Test', packages: [$package]);

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings
        );

        $this->expectNotToPerformAssertions();
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validation throws LocalizedException when required fields are missing.
     *
     * @return void
     */
    public function testValidateMissingRequiredFieldsThrowsException(): void
    {
        $shipmentData = new DhlShipmentData();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Sender Name or First & Last Name is required.');
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validation does not throw exception when package weight is zero.
     *
     * @return void
     */
    public function testValidateAllowsZeroWeightPackage(): void
    {
        $sender = new SenderData(
            name: 'Sender',
            street: 'Street',
            houseNumber: '1',
            postalCode: '00-000',
            city: 'City'
        );
        $recipient = new RecipientData(
            firstName: 'Jan',
            lastName: 'Kowalski',
            street: 'Street',
            houseNumber: '2',
            postalCode: '00-000',
            city: 'City'
        );
        $package = new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 0.0);
        $settings = new ShipmentSettingsData(content: 'Test', packages: [$package]);

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings
        );

        $this->validator->validate($shipmentData);
        $this->addToAssertionCount(1);
    }

    /**
     * Test validation passes without exception when package contents description is empty.
     *
     * @return void
     */
    public function testValidateAllowsEmptyPackageContentDescription(): void
    {
        $sender = new SenderData(
            name: 'Sender',
            street: 'Street',
            houseNumber: '1',
            postalCode: '00-000',
            city: 'City'
        );
        $recipient = new RecipientData(
            firstName: 'Jan',
            lastName: 'Kowalski',
            street: 'Street',
            houseNumber: '2',
            postalCode: '00-000',
            city: 'City'
        );
        $package = new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 1.0);
        $settings = new ShipmentSettingsData(content: '', packages: [$package]);

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings
        );

        $this->validator->validate($shipmentData);
        $this->addToAssertionCount(1);
    }

    /**
     * Test validate passes without exception when special services (insurance, ROD, COD) are selected without values.
     *
     * @return void
     */
    public function testValidateSpecialServicesWithoutValuesPasses(): void
    {
        $sender = new SenderData(name: 'S', street: 'St', houseNumber: '1', postalCode: '00-000', city: 'C');
        $recipient = new RecipientData(
            firstName: 'Jan',
            lastName: 'K',
            street: 'St',
            houseNumber: '2',
            postalCode: '00-000',
            city: 'C'
        );
        $packages = [new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 1.0)];
        $settings = new ShipmentSettingsData(serviceType: 'AH', content: 'Test', packages: $packages);
        $specialServices = new \DhlPoland\Dhl\Model\Shipment\Data\SpecialServicesData(
            insurance: true,
            insuranceValue: null,
            collectOnDelivery: true,
            collectOnDeliveryValue: null,
            returnOnDelivery: true,
            returnOnDeliveryReference: ''
        );

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings,
            specialServices: $specialServices
        );

        $this->expectNotToPerformAssertions();
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validate throws LocalizedException when pickupHourFrom is greater than or equal to pickupHourTo.
     *
     * @return void
     */
    public function testValidatePickupHourFromLaterThanToThrowsException(): void
    {
        $sender = new SenderData(name: 'S', street: 'St', houseNumber: '1', postalCode: '00-000', city: 'C');
        $recipient = new RecipientData(
            firstName: 'J',
            lastName: 'K',
            street: 'St',
            houseNumber: '2',
            postalCode: '00-000',
            city: 'C',
        );
        $packages = [new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 1.0)];
        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            content: 'Test',
            pickupHourFrom: '14:00',
            pickupHourTo: '10:00',
            packages: $packages
        );

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings,
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Courier Pickup Time [From] must be earlier than Pickup Time [To].');
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validateRawPayload passes with valid numeric fields using dot as decimal separator.
     *
     * @return void
     */
    public function testValidateRawPayloadWithValidDataPasses(): void
    {
        $payload = [
            'shipmentSettings' => [
                'packages' => [
                    ['weight' => '2.5', 'width' => '10.0', 'length' => '15.5', 'height' => '5.0', 'quantity' => '1']
                ],
                'returnLabelExpDays' => '14'
            ],
            'specialServices' => [
                'insuranceValue' => '100.50',
                'codAmount' => '50.00'
            ],
            'customs' => [
                'costsOfShipment' => '12.00',
                'grossWeight' => '2.5',
                'customsItems' => [
                    ['weight' => '1.25', 'value' => '99.99', 'quantity' => '2']
                ]
            ]
        ];

        $this->expectNotToPerformAssertions();
        $this->validator->validateRawPayload($payload);
    }

    /**
     * Test validateRawPayload throws LocalizedException when package weight contains a comma.
     *
     * @return void
     */
    public function testValidateRawPayloadWithCommaInPackageWeightThrowsException(): void
    {
        $payload = [
            'shipmentSettings' => [
                'packages' => [
                    ['weight' => '2,5', 'quantity' => '1']
                ]
            ]
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Field "Package #1 weight" cannot contain a comma. Please use a dot (.) as decimal separator.'
        );
        $this->validator->validateRawPayload($payload);
    }

    /**
     * Test validateRawPayload throws LocalizedException when package inside packages_fieldset contains a comma.
     *
     * @return void
     */
    public function testValidateRawPayloadWithCommaInPackagesFieldsetThrowsException(): void
    {
        $payload = [
            'packages_fieldset' => [
                'packages' => [
                    ['weight' => '3,5', 'quantity' => '1']
                ]
            ]
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Field "Package #1 weight" cannot contain a comma. Please use a dot (.) as decimal separator.'
        );
        $this->validator->validateRawPayload($payload);
    }

    /**
     * Test validateRawPayload throws LocalizedException when insurance value contains a comma.
     *
     * @return void
     */
    public function testValidateRawPayloadWithCommaInInsuranceValueThrowsException(): void
    {
        $payload = [
            'specialServices' => [
                'insuranceValue' => '150,50'
            ]
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Field "Insurance Value" cannot contain a comma. Please use a dot (.) as decimal separator.'
        );
        $this->validator->validateRawPayload($payload);
    }

    /**
     * Test validateRawPayload throws LocalizedException when customs item value contains a comma.
     *
     * @return void
     */
    public function testValidateRawPayloadWithCommaInCustomsValueThrowsException(): void
    {
        $payload = [
            'customs' => [
                'customsItems' => [
                    ['value' => '10,99']
                ]
            ]
        ];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Field "Customs Item #1 value" cannot contain a comma. Please use a dot (.) as decimal separator.'
        );
        $this->validator->validateRawPayload($payload);
    }
}
