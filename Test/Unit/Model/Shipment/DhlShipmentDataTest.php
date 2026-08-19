<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use PHPUnit\Framework\TestCase;

class DhlShipmentDataTest extends TestCase
{
    /**
     * Test constructor parameter assignment and getters.
     *
     * @return void
     */
    public function testConstructAndGetters(): void
    {
        $sender = new SenderData(name: 'Sender Company');
        $recipient = new RecipientData(name: 'Recipient Company');
        $services = new SpecialServicesData(cod: true, codAmount: 100.0);
        $settings = new ShipmentSettingsData(serviceType: 'AH');

        $shipmentData = new DhlShipmentData(
            orderId: 101,
            sender: $sender,
            recipient: $recipient,
            specialServices: $services,
            shipmentSettings: $settings
        );

        $this->assertEquals(101, $shipmentData->getOrderId());
        $this->assertEquals('Sender Company', $shipmentData->getSender()->getName());
        $this->assertEquals('Recipient Company', $shipmentData->getRecipient()->getName());
        $this->assertTrue($shipmentData->getSpecialServices()->isCod());
        $this->assertEquals('AH', $shipmentData->getShipmentSettings()->getServiceType());
    }

    /**
     * Test array serialization and deserialization via toArray and fromArray.
     *
     * @return void
     */
    public function testToArrayAndFromArray(): void
    {
        $data = [
            'orderId' => 202,
            'sender' => (new SenderData(name: 'Sender Inc'))->toArray(),
            'recipient' => (new RecipientData(name: 'Recipient Ltd'))->toArray(),
            'specialServices' => (new SpecialServicesData(insurance: true, insuranceValue: 500.0))->toArray(),
            'shipmentSettings' => (new ShipmentSettingsData(serviceType: '09'))->toArray(),
        ];

        $shipmentData = DhlShipmentData::fromArray($data);
        $this->assertEquals(202, $shipmentData->getOrderId());
        $this->assertEquals('Sender Inc', $shipmentData->getSender()->getName());
        $this->assertEquals('09', $shipmentData->getShipmentSettings()->getServiceType());

        $exported = $shipmentData->toArray();
        $this->assertEquals($data, $exported);
    }
}
