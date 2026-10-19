<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Source;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\Collection;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use DhlPoland\Dhl\Model\Source\ShipmentStatus;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ShipmentStatusTest extends TestCase
{
    private CollectionFactory|MockObject $collectionFactoryMock;
    private ShipmentStatus $source;

    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->source = new ShipmentStatus($this->collectionFactoryMock);
    }

    public function testToOptionArrayWithDynamicTrackingStatuses(): void
    {
        $collectionMock = $this->createMock(Collection::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectMock = $this->createMock(Select::class);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $collectionMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($connectionMock);

        $collectionMock->expects($this->once())
            ->method('getMainTable')
            ->willReturn('dhlpoland_shipment');

        $connectionMock->expects($this->once())
            ->method('select')
            ->willReturn($selectMock);

        $selectMock->expects($this->once())
            ->method('from')
            ->with('dhlpoland_shipment', [ShipmentDataInterface::STATUS])
            ->willReturnSelf();

        $selectMock->expects($this->once())
            ->method('distinct')
            ->with(true)
            ->willReturnSelf();

        $selectMock->expects($this->exactly(3))
            ->method('where')
            ->willReturnSelf();

        $connectionMock->expects($this->once())
            ->method('fetchCol')
            ->with($selectMock)
            ->willReturn(['Doręczono do odbiorcy', 'W doręczeniu']);

        $options = $this->source->toOptionArray();

        $this->assertCount(4, $options);
        $this->assertEquals(
            [
                ['value' => ShipmentDataInterface::STATUS_NEW, 'label' => __('New')],
                ['value' => ShipmentDataInterface::STATUS_COURIER_BOOKED, 'label' => __('Courier Booked')],
                ['value' => 'Doręczono do odbiorcy', 'label' => __('Doręczono do odbiorcy')],
                ['value' => 'W doręczeniu', 'label' => __('W doręczeniu')],
            ],
            $options
        );
    }

    public function testToArrayReturnsAssociativeMap(): void
    {
        $collectionMock = $this->createMock(Collection::class);
        $connectionMock = $this->createMock(AdapterInterface::class);
        $selectMock = $this->createMock(Select::class);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $collectionMock->expects($this->once())
            ->method('getConnection')
            ->willReturn($connectionMock);

        $collectionMock->expects($this->once())
            ->method('getMainTable')
            ->willReturn('dhlpoland_shipment');

        $connectionMock->expects($this->once())
            ->method('select')
            ->willReturn($selectMock);

        $selectMock->expects($this->once())
            ->method('from')
            ->willReturnSelf();
        $selectMock->expects($this->once())
            ->method('distinct')
            ->willReturnSelf();
        $selectMock->expects($this->exactly(3))
            ->method('where')
            ->willReturnSelf();

        $connectionMock->expects($this->once())
            ->method('fetchCol')
            ->willReturn(['Doręczono']);

        $map = $this->source->toArray();

        $this->assertCount(3, $map);
        $this->assertEquals(
            [
                ShipmentDataInterface::STATUS_NEW => __('New'),
                ShipmentDataInterface::STATUS_COURIER_BOOKED => __('Courier Booked'),
                'Doręczono' => __('Doręczono'),
            ],
            $map
        );
    }

    public function testToOptionArrayHandlesDatabaseExceptionGracefully(): void
    {
        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willThrowException(new RuntimeException('Database unreachable'));

        $options = $this->source->toOptionArray();

        $this->assertCount(2, $options);
        $this->assertEquals(
            [
                ['value' => ShipmentDataInterface::STATUS_NEW, 'label' => __('New')],
                ['value' => ShipmentDataInterface::STATUS_COURIER_BOOKED, 'label' => __('Courier Booked')],
            ],
            $options
        );
    }
}
