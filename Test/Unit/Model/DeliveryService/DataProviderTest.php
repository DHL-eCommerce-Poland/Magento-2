<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\DeliveryService;

use DhlPoland\Dhl\Model\DeliveryService;
use DhlPoland\Dhl\Model\DeliveryService\DataProvider;
use DhlPoland\Dhl\Model\ResourceModel\DeliveryService\Collection;
use DhlPoland\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory;
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
        $this->assertSame(14, $data[1]['return_label_exp_days']);
    }

    public function testGetDataPreservesValidReturnLabelExpDays(): void
    {
        $item = $this->createStub(DeliveryService::class);
        $item->method('getId')->willReturn(2);
        $item->method('getData')->willReturn([
            'entity_id' => 2,
            'return_label_exp_days' => 25,
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
        $this->assertSame(25, $data[2]['return_label_exp_days']);
    }
}
