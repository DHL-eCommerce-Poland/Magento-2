<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\OrderToShipmentDataMapper;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Ideo\Dhl\Model\Shipment\ShipmentDataValidator;
use Ideo\Dhl\Model\ShipmentDataFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ShipmentDataServiceTest extends TestCase
{
    /**
     * Test prepareShipmentData returns mapped DhlShipmentData object.
     *
     * @return void
     */
    public function testPrepareShipmentDataNew(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createMock(OrderToShipmentDataMapper::class);
        $validator = $this->createStub(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createStub(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);

        $order = $this->createStub(OrderInterface::class);
        $orderRepository->method('get')->willReturn($order);
        $shipmentDataRepository->method('getByOrderId')->willReturn(null);
        $dropOffPointRepository->method('getDefaultByWebsiteId')->willReturn(null);

        $expectedData = new DhlShipmentData(orderId: 101);
        $mapper->expects($this->once())
            ->method('map')
            ->willReturn($expectedData);

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory
        );

        $result = $service->prepareShipmentData(101);
        $this->assertSame($expectedData, $result);
    }

    /**
     * Test saveShipmentData validates and persists entity via repository.
     *
     * @return void
     */
    public function testSaveShipmentData(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createStub(OrderToShipmentDataMapper::class);
        $validator = $this->createMock(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);

        $shipmentData = new DhlShipmentData(orderId: 102);
        $entity = $this->createMock(ShipmentDataInterface::class);

        $validator->expects($this->once())->method('validate');
        $shipmentDataRepository->method('getByOrderId')->willReturn(null);
        $shipmentDataFactory->method('create')->willReturn($entity);

        $entity->expects($this->once())->method('setOrderId')->with(102);
        $entity->expects($this->once())->method('setStatus')->with('pending');
        $shipmentDataRepository->expects($this->once())->method('save')->willReturn($entity);

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory
        );

        $saved = $service->saveShipmentData($shipmentData);
        $this->assertSame($entity, $saved);
    }
}
