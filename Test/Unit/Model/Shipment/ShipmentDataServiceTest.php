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
use Magento\Framework\Serialize\Serializer\Json;
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

        $serializer = new Json();

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
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

        $serializer = new Json();

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
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

        $serializer = new Json();

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
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
        $serializer = new Json();

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
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $result = $service->loadShipmentData(5);
        $this->assertEquals(105, $result->getOrderId());
    }

    /**
     * Test hasShipmentForOrder returns true when repository returns an existing shipment entity.
     *
     * @return void
     */
    public function testHasShipmentForOrderReturnsTrueWhenEntityExists(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createStub(OrderToShipmentDataMapper::class);
        $validator = $this->createStub(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);
        $serializer = new Json();

        $entity = $this->createStub(ShipmentDataInterface::class);
        $shipmentDataRepository->expects($this->once())
            ->method('getByOrderId')
            ->with(101)
            ->willReturn($entity);

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $this->assertTrue($service->hasShipmentForOrder(101));
    }

    /**
     * Test hasShipmentForOrder returns false when repository returns null.
     *
     * @return void
     */
    public function testHasShipmentForOrderReturnsFalseWhenNoEntity(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createStub(OrderToShipmentDataMapper::class);
        $validator = $this->createStub(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);
        $serializer = new Json();

        $shipmentDataRepository->expects($this->once())
            ->method('getByOrderId')
            ->with(102)
            ->willReturn(null);

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $this->assertFalse($service->hasShipmentForOrder(102));
    }

    /**
     * Test saveShipmentDataWithStatus maps packageNumbers to individual packages in stored JSON payload.
     *
     * @return void
     */
    public function testSaveShipmentDataWithStatusMapsPackageNumbersToDynamicRows(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createStub(OrderToShipmentDataMapper::class);
        $validator = $this->createStub(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);
        $serializer = new Json();

        $shipmentSettings = new \Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData(
            packages: [
                ['packageType' => 'PACKAGE', 'weight' => 2.5],
                ['packageType' => 'PACKAGE', 'weight' => 5.0],
            ]
        );

        $shipmentData = new DhlShipmentData(orderId: 200, shipmentSettings: $shipmentSettings);
        $entity = $this->createMock(ShipmentDataInterface::class);

        $shipmentDataFactory->method('create')->willReturn($entity);

        $capturedJson = null;
        $entity->expects($this->once())
            ->method('setShipmentData')
            ->willReturnCallback(function (string $json) use (&$capturedJson, $entity) {
                $capturedJson = $json;
                return $entity;
            });

        $shipmentDataRepository->expects($this->once())
            ->method('save')
            ->willReturn($entity);

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $packageNumbers = ['JJD000030025688110000001106', 'JJD000030025688110000001117'];

        $service->saveShipmentDataWithStatus(
            $shipmentData,
            'Utworzono',
            null,
            null,
            '90040027827',
            null,
            $packageNumbers
        );

        $this->assertNotNull($capturedJson);
        $decoded = json_decode($capturedJson, true);

        $this->assertEquals($packageNumbers, $decoded['packageNumbers']);
        $this->assertEquals('JJD000030025688110000001106', $decoded['packages'][0]['packageNumber']);
        $this->assertEquals('JJD000030025688110000001117', $decoded['packages'][1]['packageNumber']);
        $this->assertEquals(
            'JJD000030025688110000001106',
            $decoded['shipmentSettings']['packages'][0]['packageNumber']
        );
        $this->assertEquals(
            'JJD000030025688110000001117',
            $decoded['shipmentSettings']['packages'][1]['packageNumber']
        );
    }

    /**
     * Test updateExistingShipmentPayload clears old returnShipmentId on entity and package rows.
     *
     * @return void
     */
    public function testUpdateExistingShipmentPayloadClearsReturnShipmentId(): void
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $mapper = $this->createStub(OrderToShipmentDataMapper::class);
        $validator = $this->createStub(ShipmentDataValidator::class);
        $dropOffPointRepository = $this->createStub(DropOffPointRepositoryInterface::class);
        $deliveryServiceRepository = $this->createStub(DeliveryServiceRepositoryInterface::class);
        $shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $shipmentDataFactory = $this->createStub(ShipmentDataFactory::class);
        $serializer = new Json();

        $shipmentSettings = new \Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData(
            packages: [
                [
                    'packageType' => 'PACKAGE',
                    'weight' => 2.5,
                    'returnShipmentId' => 'OLD_RET_123',
                ],
            ]
        );

        $shipmentData = new DhlShipmentData(orderId: 200, shipmentSettings: $shipmentSettings);
        $entity = $this->createMock(ShipmentDataInterface::class);

        $entity->expects($this->once())
            ->method('setReturnShipmentId')
            ->with(null);

        $shipmentDataRepository->method('getById')->with(15)->willReturn($entity);
        $shipmentDataRepository->method('save')->willReturn($entity);

        $capturedJson = null;
        $entity->expects($this->once())
            ->method('setShipmentData')
            ->willReturnCallback(function (string $json) use (&$capturedJson, $entity) {
                $capturedJson = $json;
                return $entity;
            });

        $service = new ShipmentDataService(
            $orderRepository,
            $mapper,
            $validator,
            $dropOffPointRepository,
            $deliveryServiceRepository,
            $shipmentDataRepository,
            $shipmentDataFactory,
            $serializer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $service->updateExistingShipmentPayload(15, $shipmentData, 'Utworzono', '90040027899');

        $this->assertNotNull($capturedJson);
        $decoded = json_decode($capturedJson, true);

        $this->assertNull($decoded['shipmentSettings']['packages'][0]['returnShipmentId']);
    }
}
