<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\ResourceModel\ShipmentData\Collection;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\DataProvider;
use Ideo\Dhl\Model\ShipmentData;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    public function testGetDataUnserializesShipmentDataPayload(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collection = $this->createStub(Collection::class);
        $serializer = $this->createStub(SerializerInterface::class);

        $shipmentDataModel = $this->createStub(ShipmentData::class);
        $shipmentDataModel->method('getId')->willReturn(42);
        $shipmentDataModel->method('getData')->willReturn([
            'entity_id' => 42,
            'order_id' => 1001,
            'shipment_id' => '99887766',
            'status' => 'CREATED',
        ]);
        $shipmentDataModel->method('getStatus')->willReturn('CREATED');
        $shipmentDataModel->method('getShipmentData')->willReturn('{"sender":{"name":"Sender Co"}}');

        $shipmentDataModel->method('getReturnShipmentId')->willReturn('RET554433');

        $collection->method('getItems')->willReturn([$shipmentDataModel]);
        $collectionFactory->method('create')->willReturn($collection);

        $payloadArray = [
            'sender' => ['name' => 'Sender Co', 'city' => 'Warsaw'],
            'recipient' => ['firstName' => 'John', 'lastName' => 'Doe'],
            'shipmentSettings' => ['serviceType' => 'AH', 'packages' => [['weight' => 2.5]]],
            'specialServices' => ['insurance' => true],
            'packageNumbers' => ['BLP10001', 'BLP10002'],
        ];

        $serializer->method('unserialize')
            ->willReturn($payloadArray);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));

        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);

        $dataProvider = new DataProvider(
            'dhlparcel_shipment_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer,
            $timezoneStub,
            $dateSanitizer
        );

        $data = $dataProvider->getData();

        $this->assertArrayHasKey(42, $data);
        $this->assertEquals('Sender Co', $data[42]['sender']['name']);
        $this->assertEquals('John', $data[42]['recipient']['firstName']);
        $this->assertEquals('AH', $data[42]['shipmentSettings']['serviceType']);
        $this->assertEquals('CREATED', $data[42]['shipmentSettings']['status']);
        $this->assertEquals('RET554433', $data[42]['shipmentSettings']['returnShipmentId']);
        $this->assertEquals('BLP10001, BLP10002', $data[42]['shipmentSettings']['packageNumbers']);
        $this->assertEquals('1', $data[42]['specialServices']['insurance']);
        $this->assertCount(1, $data[42]['packages']);
        $this->assertEquals('BLP10001', $data[42]['packages'][0]['packageNumber']);
    }
}
