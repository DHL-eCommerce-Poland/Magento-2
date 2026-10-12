<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Ui\Component\Listing\Column;

use DhlPoland\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use DhlPoland\Dhl\Ui\Component\Listing\Column\ReturnShipmentId;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class ReturnShipmentIdTest extends TestCase
{
    /**
     * @var ReturnShipmentId
     */
    private ReturnShipmentId $column;

    protected function setUp(): void
    {
        $contextMock = $this->createStub(ContextInterface::class);
        $uiComponentFactoryMock = $this->createStub(UiComponentFactory::class);

        $this->column = new ReturnShipmentId(
            $contextMock,
            $uiComponentFactoryMock,
            new ShipmentDataPayloadReader(new Json()),
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

    public function testPrepareDataSourceReadsReturnIdsFromNestedPackages(): void
    {
        $payload = json_encode([
            'shipmentSettings' => [
                'packages' => [
                    ['returnShipmentId' => '90040025505'],
                    ['returnShipmentId' => ['invalid']],
                    ['returnShipmentId' => '90040025505'],
                ],
            ],
        ]);

        $result = $this->column->prepareDataSource([
            'data' => [
                'items' => [
                    [
                        'entity_id' => 4,
                        'return_shipment_id' => null,
                        'shipment_data' => $payload,
                    ],
                ],
            ],
        ]);

        $this->assertSame('90040025505', $result['data']['items'][0]['return_shipment_id']);
    }
}
