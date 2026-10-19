<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Ui\Component\Listing\Column;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Ui\Component\Listing\Column\Status;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    private Status $column;

    protected function setUp(): void
    {
        $contextMock = $this->createStub(ContextInterface::class);
        $uiComponentFactoryMock = $this->createStub(UiComponentFactory::class);
        $escaper = new Escaper();

        $this->column = new Status(
            $contextMock,
            $uiComponentFactoryMock,
            $escaper,
            [],
            ['name' => ShipmentDataInterface::STATUS]
        );
    }

    public function testPrepareDataSourceFormatsStandardStatuses(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 1,
                        ShipmentDataInterface::STATUS => ShipmentDataInterface::STATUS_NEW,
                    ],
                    [
                        'entity_id' => 2,
                        ShipmentDataInterface::STATUS => ShipmentDataInterface::STATUS_COURIER_BOOKED,
                    ],
                ],
            ],
        ];

        $result = $this->column->prepareDataSource($dataSource);

        $this->assertEquals('New', $result['data']['items'][0][ShipmentDataInterface::STATUS]);
        $this->assertEquals('Courier Booked', $result['data']['items'][1][ShipmentDataInterface::STATUS]);
    }

    public function testPrepareDataSourceRendersTrackingStatusFromApi(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 3,
                        ShipmentDataInterface::STATUS => 'Przesyłka doręczona do odbiorcy',
                    ],
                ],
            ],
        ];

        $result = $this->column->prepareDataSource($dataSource);

        $this->assertEquals(
            'Przesyłka doręczona do odbiorcy',
            $result['data']['items'][0][ShipmentDataInterface::STATUS]
        );
    }

    public function testPrepareDataSourceEscapesHtml(): void
    {
        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 4,
                        ShipmentDataInterface::STATUS => '<script>alert("xss")</script>',
                    ],
                ],
            ],
        ];

        $result = $this->column->prepareDataSource($dataSource);

        $this->assertStringNotContainsString('<script>', $result['data']['items'][0][ShipmentDataInterface::STATUS]);
        $this->assertStringContainsString('&lt;script&gt;', $result['data']['items'][0][ShipmentDataInterface::STATUS]);
    }

    public function testPrepareDataSourceHandlesEmptyDataGracefully(): void
    {
        $dataSource = ['data' => []];
        $this->assertSame($dataSource, $this->column->prepareDataSource($dataSource));
    }
}
