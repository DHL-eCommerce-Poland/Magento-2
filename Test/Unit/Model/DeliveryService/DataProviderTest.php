<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\DeliveryService;

use Ideo\Dhl\Model\DeliveryService;
use Ideo\Dhl\Model\DeliveryService\DataProvider;
use Ideo\Dhl\Model\ResourceModel\DeliveryService\Collection;
use Ideo\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    public function testGetDataExplodesMethodCodeStringToArray(): void
    {
        $item = $this->createStub(DeliveryService::class);
        $item->method('getId')->willReturn(1);
        $item->method('getData')->willReturn([
            'entity_id' => 1,
            'method_code' => 'dhl_courier,dhl_express',
            'additional_services' => '{"insurance":"1"}',
        ]);

        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturn([$item]);

        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $dataProvider = new DataProvider(
            'dhlparcel_delivery_service_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer
        );

        $data = $dataProvider->getData();

        $this->assertArrayHasKey(1, $data);
        $this->assertSame(['dhl_courier', 'dhl_express'], $data[1]['method_code']);
        $this->assertSame(['insurance' => '1'], $data[1]['additional_services']);
    }
}
