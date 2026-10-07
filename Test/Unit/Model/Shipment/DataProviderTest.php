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
            'shipmentSettings' => [
                'serviceType' => 'AH',
                'packages' => [
                    [
                        'weight' => 2.5,
                        'returnShipmentId' => 'RET554433',
                    ]
                ]
            ],
            'specialServices' => ['insurance' => true],
        ];

        $serializer->method('unserialize')
            ->willReturn($payloadArray);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));

        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);

        $dataProvider = new DataProvider(
            'dhlpoland_shipment_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer,
            $timezoneStub,
            $dateSanitizer,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            $this->createStub(\Ideo\Dhl\Api\DeliveryServiceRepositoryInterface::class)
        );

        $data = $dataProvider->getData();

        $this->assertArrayHasKey(42, $data);
        $this->assertEquals('Sender Co', $data[42]['sender']['name']);
        $this->assertEquals('John', $data[42]['recipient']['firstName']);
        $this->assertEquals('AH', $data[42]['shipmentSettings']['serviceType']);
        $this->assertEquals('CREATED', $data[42]['shipmentSettings']['status']);
        $this->assertEquals('RET554433', $data[42]['shipmentSettings']['returnShipmentId']);
        $this->assertArrayNotHasKey('packages', $data[42]['shipmentSettings']);
        $this->assertCount(1, $data[42]['packages']);
        $this->assertEquals('RET554433', $data[42]['packages'][0]['returnShipmentId']);
        $this->assertArrayHasKey('packages_fieldset', $data[42]);
        $this->assertCount(1, $data[42]['packages_fieldset']['packages']);
    }

    public function testGetDataNormalizesPackagesWithFallbackFromRootPackageNumbers(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collection = $this->createStub(Collection::class);
        $serializer = $this->createStub(SerializerInterface::class);

        $shipmentDataModel = $this->createStub(ShipmentData::class);
        $shipmentDataModel->method('getId')->willReturn(10);
        $shipmentDataModel->method('getData')->willReturn(['entity_id' => 10]);
        $shipmentDataModel->method('getStatus')->willReturn('CREATED');
        $shipmentDataModel->method('getShipmentData')->willReturn('{"test":true}');

        $collection->method('getItems')->willReturn([$shipmentDataModel]);
        $collectionFactory->method('create')->willReturn($collection);

        $payloadArray = [
            'packageNumbers' => ['JJD000030025688110000001106', 'JJD000030025688110000001117'],
            'shipmentSettings' => [
                'packages' => [
                    ['weight' => 2.5],
                    ['weight' => 5.0],
                ]
            ],
        ];

        $serializer->method('unserialize')->willReturn($payloadArray);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));
        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);

        $dataProvider = new DataProvider(
            'dhlpoland_shipment_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer,
            $timezoneStub,
            $dateSanitizer,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            $this->createStub(\Ideo\Dhl\Api\DeliveryServiceRepositoryInterface::class)
        );

        $data = $dataProvider->getData();

        $this->assertEquals('JJD000030025688110000001106', $data[10]['packages'][0]['packageNumber']);
        $this->assertEquals('JJD000030025688110000001117', $data[10]['packages'][1]['packageNumber']);
    }

    public function testGetDataLoadsReturnLabelExpDaysFromTemplateWhenEmpty(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collection = $this->createStub(Collection::class);
        $serializer = $this->createStub(SerializerInterface::class);

        $shipmentDataModel = $this->createStub(ShipmentData::class);
        $shipmentDataModel->method('getId')->willReturn(3);
        $shipmentDataModel->method('getData')->willReturn(['entity_id' => 3]);
        $shipmentDataModel->method('getStatus')->willReturn('CREATED');
        $shipmentDataModel->method('getReturnShipmentId')->willReturn('90040150425');
        $shipmentDataModel->method('getDeliveryServiceTemplateId')->willReturn('1');
        $shipmentDataModel->method('getShipmentData')->willReturn('{"test":true}');

        $collection->method('getItems')->willReturn([$shipmentDataModel]);
        $collectionFactory->method('create')->willReturn($collection);

        $payloadArray = [
            'shipmentSettings' => [
                'returnLabelExpDays' => 0,
                'autoCreateReturnShipment' => false,
            ],
        ];
        $serializer->method('unserialize')->willReturn($payloadArray);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));
        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);

        $deliveryServiceTemplate = $this->createStub(\Ideo\Dhl\Api\Data\DeliveryServiceInterface::class);
        $deliveryServiceTemplate->method('getReturnLabelExpDays')->willReturn(21);
        $deliveryServiceTemplate->method('getReturnLabelType')->willReturn('BLP');
        $deliveryServiceTemplate->method('getReturnQrCodeType')->willReturn('QR_PDF');
        $deliveryServiceTemplate->method('getReturnBookCourier')->willReturn(1);

        $deliveryServiceRepo = $this->createStub(\Ideo\Dhl\Api\DeliveryServiceRepositoryInterface::class);
        $deliveryServiceRepo->method('getById')->willReturn($deliveryServiceTemplate);

        $dataProvider = new DataProvider(
            'dhlpoland_shipment_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer,
            $timezoneStub,
            $dateSanitizer,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            $deliveryServiceRepo
        );

        $data = $dataProvider->getData();

        $this->assertEquals(21, $data[3]['shipmentSettings']['returnLabelExpDays']);
        $this->assertEquals('0', $data[3]['shipmentSettings']['autoCreateReturnShipment']);
        $this->assertEquals('BLP', $data[3]['shipmentSettings']['returnLabelType']);
        $this->assertEquals('QR_PDF', $data[3]['shipmentSettings']['returnQrCodeType']);
        $this->assertEquals(1, $data[3]['shipmentSettings']['returnBookCourier']);
    }

    public function testGetDataNormalizesPackagesWithQuantityGreaterThanOne(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collection = $this->createStub(Collection::class);
        $serializer = $this->createStub(SerializerInterface::class);

        $shipmentDataModel = $this->createStub(ShipmentData::class);
        $shipmentDataModel->method('getId')->willReturn(20);
        $shipmentDataModel->method('getData')->willReturn(['entity_id' => 20]);
        $shipmentDataModel->method('getStatus')->willReturn('CREATED');
        $shipmentDataModel->method('getReturnShipmentId')->willReturn('RET001, RET002, RET003');
        $shipmentDataModel->method('getShipmentData')->willReturn('{"test":true}');

        $collection->method('getItems')->willReturn([$shipmentDataModel]);
        $collectionFactory->method('create')->willReturn($collection);

        $payloadArray = [
            'packageNumbers' => ['JJD001', 'JJD002', 'JJD003'],
            'shipmentSettings' => [
                'packages' => [
                    ['quantity' => 2, 'weight' => 5.0],
                    ['quantity' => 1, 'weight' => 2.0],
                ],
            ],
        ];

        $serializer->method('unserialize')->willReturn($payloadArray);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));
        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);

        $dataProvider = new DataProvider(
            'dhlpoland_shipment_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer,
            $timezoneStub,
            $dateSanitizer,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            $this->createStub(\Ideo\Dhl\Api\DeliveryServiceRepositoryInterface::class)
        );

        $data = $dataProvider->getData();

        $this->assertEquals('JJD001, JJD002', $data[20]['packages'][0]['packageNumber']);
        $this->assertEquals('JJD003', $data[20]['packages'][1]['packageNumber']);
        $this->assertEquals('RET001, RET002', $data[20]['packages'][0]['returnShipmentId']);
        $this->assertEquals('RET003', $data[20]['packages'][1]['returnShipmentId']);
    }

    public function testGetDataNormalizesCustomsData(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collection = $this->createStub(Collection::class);
        $serializer = $this->createStub(SerializerInterface::class);

        $shipmentDataModel = $this->createStub(ShipmentData::class);
        $shipmentDataModel->method('getId')->willReturn(15);
        $shipmentDataModel->method('getData')->willReturn(['entity_id' => 15]);
        $shipmentDataModel->method('getStatus')->willReturn('CREATED');
        $shipmentDataModel->method('getCustomsTemplateId')->willReturn('5');
        $shipmentDataModel->method('getShipmentData')->willReturn('{"customs":{}}');

        $collection->method('getItems')->willReturn([$shipmentDataModel]);
        $collectionFactory->method('create')->willReturn($collection);

        $payloadArray = [
            'customs' => [
                'customsType' => 'I',
                'categoryOfItem' => '11',
                'currency' => 'EUR',
                'countryOfOrigin' => 'DE',
                'costsOfShipment' => 15.5,
                'grossWeight' => 3.2,
                'firstName' => 'Anna',
                'secondaryName' => 'Kowalska',
                'nipNr' => '1234567890',
                'eoriNr' => 'DE12345678',
                'eoriNrReceiver' => 'PL987654321',
                'invoiceNr' => 'FV/2026/001',
                'invoiceDate' => '2026-10-07',
                'invoiceFile' => '/media/dhl/invoices/inv_001.pdf',
                'notExceedValue' => true,
                'notProhibitedGoods' => true,
                'notRestrictedGoods' => false,
                'invoiceContent' => false,
                'customsItems' => [
                    [
                        'nameEn' => 'T-Shirt Cotton',
                        'namePl' => 'Koszulka bawełniana',
                        'quantity' => 2,
                        'weight' => 0.5,
                        'value' => 25.0,
                        'tariffCode' => '61091000',
                        'countryOfOrigin' => 'DE',
                    ],
                    [
                        'nameEn' => 'Blank fields',
                    ],
                ],
            ],
        ];

        $serializer->method('unserialize')->willReturn($payloadArray);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-10-07'));
        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);

        $dataProvider = new DataProvider(
            'dhlpoland_shipment_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $serializer,
            $timezoneStub,
            $dateSanitizer,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            $this->createStub(\Ideo\Dhl\Api\DeliveryServiceRepositoryInterface::class)
        );

        $data = $dataProvider->getData();

        $this->assertArrayHasKey(15, $data);
        $this->assertArrayHasKey('customs', $data[15]);
        $this->assertArrayHasKey('customs_fieldset', $data[15]);

        $customs = $data[15]['customs'];
        $this->assertEquals(5, $customs['selectedCustomsTemplateId']);
        $this->assertEquals('I', $customs['customsType']);
        $this->assertEquals('11', $customs['categoryOfItem']);
        $this->assertEquals('EUR', $customs['currency']);
        $this->assertEquals('DE', $customs['countryOfOrigin']);
        $this->assertEquals(15.5, $customs['costsOfShipment']);
        $this->assertEquals(3.2, $customs['grossWeight']);
        $this->assertEquals('Anna', $customs['firstName']);
        $this->assertEquals('Kowalska', $customs['secondaryName']);
        $this->assertEquals('1234567890', $customs['nipNr']);
        $this->assertEquals('DE12345678', $customs['eoriNr']);
        $this->assertEquals('/media/dhl/invoices/inv_001.pdf', $customs['invoiceFile']);

        $this->assertEquals('1', $customs['notExceedValue']);
        $this->assertEquals('1', $customs['notProhibitedGoods']);
        $this->assertEquals('0', $customs['notRestrictedGoods']);
        $this->assertEquals('0', $customs['invoiceContent']);

        $this->assertCount(2, $customs['customsItems']);
        $this->assertEquals('0', $customs['customsItems'][0]['record_id']);
        $this->assertEquals('T-Shirt Cotton', $customs['customsItems'][0]['nameEn']);
        $this->assertEquals('61091000', $customs['customsItems'][0]['tariffCode']);
        $this->assertSame('', $customs['customsItems'][1]['namePl']);
        $this->assertSame('', $customs['customsItems'][1]['quantity']);
        $this->assertSame('', $customs['customsItems'][1]['weight']);
        $this->assertSame('', $customs['customsItems'][1]['value']);
        $this->assertSame('', $customs['customsItems'][1]['tariffCode']);
        $this->assertSame('', $customs['customsItems'][1]['countryOfOrigin']);
    }
}

