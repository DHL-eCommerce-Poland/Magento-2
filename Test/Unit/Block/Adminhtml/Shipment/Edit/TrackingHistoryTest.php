<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Block\Adminhtml\Shipment\Edit;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Block\Adminhtml\Shipment\Edit\TrackingHistory;
use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TrackingHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManager::class));
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(ObjectManager::class);
        if ($reflection->hasProperty('_instance')) {
            $property = $reflection->getProperty('_instance');
            $property->setValue(null, null);
        }
    }

    public function testReturnsTrackingEventsAndShipmentIdFromTheSameLoadedEntity(): void
    {
        $events = [['status' => 'Delivered']];
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentData')->willReturn('{"trackingEvents":[]}');
        $shipment->method('getShipmentId')->willReturn('123456');
        $shipment->method('getEntityId')->willReturn(12);

        $shipmentData = $this->createMock(AdminhtmlShipmentData::class);
        $shipmentData->expects($this->exactly(2))->method('getShipment')->willReturn($shipment);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('unserialize')
            ->with('{"trackingEvents":[]}')
            ->willReturn(['trackingEvents' => $events]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $block = new TrackingHistory($this->createStub(Context::class), $shipmentData, $serializer, $logger);

        $this->assertSame($events, $block->getTrackingEvents());
        $this->assertSame('123456', $block->getShipmentId());
    }

    public function testLogsMalformedSerializedPayloadAndReturnsNoEvents(): void
    {
        $exception = new \RuntimeException('Invalid serialized data.');
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentData')->willReturn('{invalid');
        $shipment->method('getEntityId')->willReturn(12);

        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('unserialize')
            ->with('{invalid')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Unable to read DHL tracking history from the shipment payload.',
                [
                    'entity_id' => 12,
                    'exception_type' => \RuntimeException::class,
                    'exception_code' => 0,
                ]
            );

        $block = new TrackingHistory($this->createStub(Context::class), $shipmentData, $serializer, $logger);

        $this->assertSame([], $block->getTrackingEvents());
    }

    public function testLogsInvalidTrackingEventsStructure(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentData')->willReturn('{"trackingEvents":"invalid"}');
        $shipment->method('getEntityId')->willReturn(12);

        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('unserialize')->willReturn(['trackingEvents' => 'invalid']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'DHL tracking history has an invalid payload structure.',
                ['entity_id' => 12]
            );

        $block = new TrackingHistory($this->createStub(Context::class), $shipmentData, $serializer, $logger);

        $this->assertSame([], $block->getTrackingEvents());
    }

    public function testLogsNonArrayPayloadAndReturnsNoEvents(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentData')->willReturn('"invalid"');
        $shipment->method('getEntityId')->willReturn(12);

        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('unserialize')
            ->willReturn('invalid');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'DHL shipment payload has an invalid structure.',
                ['entity_id' => 12]
            );

        $block = new TrackingHistory($this->createStub(Context::class), $shipmentData, $serializer, $logger);

        $this->assertSame([], $block->getTrackingEvents());
    }

    public function testReturnsEmptyHistoryWhenShipmentIsUnavailable(): void
    {
        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getShipment')->willReturn(null);
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('unserialize');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $block = new TrackingHistory($this->createStub(Context::class), $shipmentData, $serializer, $logger);

        $this->assertSame([], $block->getTrackingEvents());
        $this->assertSame('', $block->getShipmentId());
    }
}
