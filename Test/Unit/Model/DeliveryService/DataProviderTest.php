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
        $methodsString = 'parcel_polska,parcel_polska_cod,premium,premium_cod,parcel_9,parcel_9_cod,'
            . 'parcel_12,parcel_12_cod,economy_box,economy_box_cod';
        $methodsArray = explode(',', $methodsString);

        $item = $this->createStub(DeliveryService::class);
        $item->method('getId')->willReturn(1);
        $item->method('getData')->willReturn([
            'entity_id' => 1,
            'method_code' => $methodsString,
            'additional_services' => '{"insurance":"1"}',
        ]);

        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturn([$item]);

        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $dataProvider = new DataProvider(
            'dhlpoland_delivery_service_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer
        );

        $data = $dataProvider->getData();

        $this->assertArrayHasKey(1, $data);
        $this->assertSame($methodsArray, $data[1]['method_code']);
        $this->assertContains('economy_box', $data[1]['method_code']);
        $this->assertContains('economy_box_cod', $data[1]['method_code']);
        $this->assertSame(['insurance' => '1'], $data[1]['additional_services']);
    }
}
