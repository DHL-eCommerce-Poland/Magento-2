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
            dispatchDate: date('Y-m-d'),
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
}
