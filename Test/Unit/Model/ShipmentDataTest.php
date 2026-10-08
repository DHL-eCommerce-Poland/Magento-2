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
        $shipmentData->setStatus('created');
        $shipmentData->setShipmentId('24123456789');
        $shipmentData->setDispatchNumber('90018060826WWW');
        $shipmentData->setReturnShipmentId('90040027838');
        $shipmentData->setShipmentData('{"test": true}');

        $this->assertEquals(10, $shipmentData->getOrderId());
        $this->assertEquals('created', $shipmentData->getStatus());
        $this->assertEquals('24123456789', $shipmentData->getShipmentId());
        $this->assertEquals('90018060826WWW', $shipmentData->getDispatchNumber());
        $this->assertEquals('90040027838', $shipmentData->getReturnShipmentId());
        $this->assertEquals('{"test": true}', $shipmentData->getShipmentData());
    }
}
