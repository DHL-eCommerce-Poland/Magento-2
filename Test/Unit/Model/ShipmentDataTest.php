<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\ResourceModel\ShipmentData as ShipmentDataResource;
use Ideo\Dhl\Model\ShipmentData;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class ShipmentDataTest extends TestCase
{
    /**
     * Test entity getters and setters for order_id, status, shipment_id, preset IDs.
     *
     * @return void
     */
    public function testGettersAndSetters(): void
    {
        $context = $this->createStub(Context::class);
        $registry = $this->createStub(Registry::class);
        $resource = $this->createStub(ShipmentDataResource::class);

        $shipmentData = new ShipmentData($context, $registry, $resource);

        $shipmentData->setOrderId(10);
        $shipmentData->setStatus(\Ideo\Dhl\Model\Shipment\ShipmentStatus::DISPATCHED);
        $shipmentData->setShipmentId('24123456789');
        $shipmentData->setPresetSenderId('1');
        $shipmentData->setPresetShipmentId('2');
        $shipmentData->setShipmentData('{"test": true}');
        $shipmentData->setErrorMessages(null);

        $this->assertEquals(10, $shipmentData->getOrderId());
        $this->assertEquals(\Ideo\Dhl\Model\Shipment\ShipmentStatus::DISPATCHED, $shipmentData->getStatus());
        $this->assertEquals('24123456789', $shipmentData->getShipmentId());
        $this->assertEquals('1', $shipmentData->getPresetSenderId());
        $this->assertEquals('2', $shipmentData->getPresetShipmentId());
        $this->assertEquals('{"test": true}', $shipmentData->getShipmentData());
        $this->assertNull($shipmentData->getErrorMessages());
    }
}
