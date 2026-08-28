<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\Data\PackageData;
use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentDataValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ShipmentDataValidatorTest extends TestCase
{
    /**
     * @var ShipmentDataValidator
     */
    private ShipmentDataValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ShipmentDataValidator();
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
     * Test validation throws LocalizedException when required fields are missing.
     *
     * @return void
     */
    public function testValidateMissingRequiredFieldsThrowsException(): void
    {
        $shipmentData = new DhlShipmentData();

        $this->expectException(LocalizedException::class);
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validation throws LocalizedException when package weight is zero.
     *
     * @return void
     */
    public function testValidateInvalidWeightThrowsException(): void
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
        $invalidPackage = new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 0.0);
        $settings = new ShipmentSettingsData(content: 'Test', packages: [$invalidPackage]);

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings
        );

        $this->expectException(LocalizedException::class);
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validation throws LocalizedException when multiple packages provided for ServicePoint.
     *
     * @return void
     */
    public function testValidateSinglePieceOnlyRestrictionThrowsException(): void
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
            city: 'City',
            popId: '4529508'
        );
        $packages = [
            new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 1.0),
            new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 1.0)
        ];
        $settings = new ShipmentSettingsData(serviceType: 'SP', content: 'Test', packages: $packages);

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings
        );

        $this->expectException(LocalizedException::class);
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validation throws LocalizedException when non-standard package provided for ServicePoint.
     *
     * @return void
     */
    public function testValidateNonStandardForbiddenThrowsException(): void
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
            city: 'City',
            popId: '4529508'
        );
        $packages = [
            new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 1.0, isNonStandard: true)
        ];
        $settings = new ShipmentSettingsData(serviceType: 'SP', content: 'Test', packages: $packages);

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings
        );

        $this->expectException(LocalizedException::class);
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validate throws LocalizedException when insurance is selected without valid value.
     *
     * @return void
     */
    public function testValidateInsuranceValueRequiredWhenSelected(): void
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
        $specialServices = new \Ideo\Dhl\Model\Shipment\Data\SpecialServicesData(insurance: true, insuranceValue: 0.0);

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings,
            specialServices: $specialServices
        );

        $this->expectException(LocalizedException::class);
        $this->validator->validate($shipmentData);
    }

    /**
     * Test validate throws LocalizedException when ROD is selected without document text.
     *
     * @return void
     */
    public function testValidateRodDocumentTextRequiredWhenSelected(): void
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
        $specialServices = new \Ideo\Dhl\Model\Shipment\Data\SpecialServicesData(rod: true, rodDocumentText: '');

        $shipmentData = new DhlShipmentData(
            orderId: 1,
            sender: $sender,
            recipient: $recipient,
            shipmentSettings: $settings,
            specialServices: $specialServices
        );

        $this->expectException(LocalizedException::class);
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
        $recipient = new RecipientData(firstName: 'J', lastName: 'K', street: 'St', houseNumber: '2', postalCode: '00-000', city: 'C');
        $packages = [new PackageData(packageType: 'PACKAGE', quantity: 1, weight: 1.0)];
        $settings = new ShipmentSettingsData(
            serviceType: 'AH',
            content: 'Test',
            pickupHourFrom: '14:00',
            pickupHourTo: '10:00',
            packages: $packages
        );

        $shipmentData = new DhlShipmentData(orderId: 1, sender: $sender, recipient: $recipient, shipmentSettings: $settings);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Courier Pickup Time [From] must be earlier than Pickup Time [To].');
        $this->validator->validate($shipmentData);
    }
}
