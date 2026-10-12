<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData as ShipmentDataResource;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\Collection;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use DhlPoland\Dhl\Model\ShipmentDataFactory;
use DhlPoland\Dhl\Model\ShipmentDataRepository;
use Magento\Framework\Api\CriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use PHPUnit\Framework\TestCase;

class ShipmentDataRepositoryTest extends TestCase
{
    public function testGetListByMagentoShipmentIdsReturnsEmptyForEmptyInput(): void
    {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects($this->never())->method('create');

        $repository = $this->createRepository($collectionFactory);

        $this->assertSame([], $repository->getListByMagentoShipmentIds([]));
    }

    public function testGetListByMagentoShipmentIdsFiltersAndSortsByNewestEntityFirst(): void
    {
        $newest = $this->createStub(ShipmentDataInterface::class);
        $older = $this->createStub(ShipmentDataInterface::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with(ShipmentDataInterface::MAGENTO_SHIPMENT_ID, ['in' => [12, 34]])
            ->willReturnSelf();
        $collection->expects($this->once())
            ->method('setOrder')
            ->with(ShipmentDataInterface::ENTITY_ID, CriteriaInterface::SORT_ORDER_DESC)
            ->willReturnSelf();
        $collection->method('getItems')->willReturn([$newest, $older]);

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $repository = $this->createRepository($collectionFactory);

        $this->assertSame([$newest, $older], $repository->getListByMagentoShipmentIds([12, 34]));
    }

    private function createRepository(CollectionFactory $collectionFactory): ShipmentDataRepository
    {
        return new ShipmentDataRepository(
            $this->createStub(ShipmentDataResource::class),
            $this->createStub(ShipmentDataFactory::class),
            $collectionFactory,
            $this->createStub(SearchResultsInterfaceFactory::class),
            $this->createStub(CollectionProcessorInterface::class)
        );
    }
}
