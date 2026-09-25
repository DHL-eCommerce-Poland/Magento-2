<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Ui\Component\Listing\Column;

use Ideo\Dhl\Ui\Component\Listing\Column\ReturnShipmentId;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class ReturnShipmentIdTest extends TestCase
{
    private ReturnShipmentId $column;

    protected function setUp(): void
    {
        $contextMock = $this->createStub(ContextInterface::class);
        $uiComponentFactoryMock = $this->createStub(UiComponentFactory::class);
        $serializerMock = $this->createStub(SerializerInterface::class);
        $serializerMock->method('unserialize')->willReturnCallback(fn($data) => json_decode($data, true));

        $this->column = new ReturnShipmentId(
            $contextMock,
            $uiComponentFactoryMock,
            $serializerMock,
            [],
            ['name' => 'return_shipment_id']
        );
    }

    public function testPrepareDataSourceDirectValue(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 1,
                        'return_shipment_id' => '90040025505, 90040025516, 90040025527',
                    ],
                ],
            ],
        ];

        $result = $this->column->prepareDataSource($dataSource);
        $this->assertEquals(
            '90040025505, 90040025516, 90040025527',
            $result['data']['items'][0]['return_shipment_id']
        );
    }

    public function testPrepareDataSourceFallbackToJsonPackages(): void
    {
        $payload = json_encode([
            'packages' => [
                ['returnShipmentId' => '90040025505'],
                ['returnShipmentId' => '90040025516'],
            ],
        ]);

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 2,
                        'return_shipment_id' => null,
                        'shipment_data' => $payload,
                    ],
                ],
            ],
        ];

        $result = $this->column->prepareDataSource($dataSource);
        $this->assertEquals(
            '90040025505, 90040025516',
            $result['data']['items'][0]['return_shipment_id']
        );
    }

    public function testPrepareDataSourceEmptyWhenNoReturn(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 3,
                        'return_shipment_id' => null,
                        'shipment_data' => null,
                    ],
                ],
            ],
        ];

        $result = $this->column->prepareDataSource($dataSource);
        $this->assertEquals('', $result['data']['items'][0]['return_shipment_id']);
    }
}
