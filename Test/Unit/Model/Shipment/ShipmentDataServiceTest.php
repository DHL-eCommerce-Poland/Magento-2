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
        $dropOffPointRepository->method('getDefault')->willReturn(null);

        $expectedData = new DhlShipmentData(orderId: 101);
        $mapper->expects($this->once())
            ->method('map')
            ->willReturn($expectedData);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer
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
        $shipmentDataFactory->method('create')->willReturn($entity);

        $entity->expects($this->once())->method('setOrderId')->with(102);
        $entity->expects($this->once())->method('setStatus')->with('Nowa');
        $shipmentDataRepository->expects($this->once())->method('save')->willReturn($entity);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer
        );

        $saved = $service->saveShipmentData($shipmentData);
        $this->assertSame($entity, $saved);
    }

    /**
     * Test getAllShipmentsByOrderId calls repository getListByOrderId.
     *
     * @return void
     */
    public function testGetAllShipmentsByOrderId(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createStub(OrderToShipmentDataMapper::class);
        $validator = $this->createStub(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);

        $entity1 = $this->createStub(ShipmentDataInterface::class);
        $entity2 = $this->createStub(ShipmentDataInterface::class);
        $shipmentDataRepository->expects($this->once())
            ->method('getListByOrderId')
            ->with(105)
            ->willReturn([$entity1, $entity2]);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer
        );

        $results = $service->getAllShipmentsByOrderId(105);
        $this->assertCount(2, $results);
        $this->assertSame([$entity1, $entity2], $results);
    }

    /**
     * Test loadShipmentData loads entity payload and returns DhlShipmentData object.
     *
     * @return void
     */
    public function testLoadShipmentData(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createStub(OrderToShipmentDataMapper::class);
        $validator = $this->createStub(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);
        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $entity = $this->createStub(ShipmentDataInterface::class);
        $entity->method('getShipmentData')->willReturn('{"orderId":105}');
        $shipmentDataRepository->expects($this->once())
            ->method('getById')
            ->with(5)
            ->willReturn($entity);

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer
        );

        $result = $service->loadShipmentData(5);
        $this->assertEquals(105, $result->getOrderId());
    }
}
