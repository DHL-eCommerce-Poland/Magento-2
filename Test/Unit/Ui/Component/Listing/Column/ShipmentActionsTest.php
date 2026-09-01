<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Ui\Component\Listing\Column;

use Ideo\Dhl\Ui\Component\Listing\Column\ShipmentActions;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class ShipmentActionsTest extends TestCase
{
    public function testPrepareDataSourceAppendsEditAndViewOrderActions(): void
    {
        $context = $this->createStub(ContextInterface::class);
        $uiComponentFactory = $this->createStub(UiComponentFactory::class);
        $urlBuilder = $this->createMock(UrlInterface::class);

        $urlBuilder->expects($this->exactly(8))
            ->method('getUrl')
            ->willReturnMap([
                ['ideo_dhl/shipment/edit', ['entity_id' => 10], 'http://example.com/dhl/shipment/edit/entity_id/10'],
                ['ideo_dhl/shipment/printLabel', ['entity_id' => 10], 'http://example.com/dhl/shipment/printLabel/entity_id/10'],
                ['ideo_dhl/shipment/createReturnShipment', ['entity_id' => 10], 'http://example.com/dhl/shipment/createReturnShipment/entity_id/10'],
                ['ideo_dhl/shipment/downloadScan', ['entity_id' => 10], 'http://example.com/dhl/shipment/downloadScan/entity_id/10'],
                ['ideo_dhl/shipment/downloadEpod', ['entity_id' => 10], 'http://example.com/dhl/shipment/downloadEpod/entity_id/10'],
                ['ideo_dhl/shipment/updateStatus', ['entity_id' => 10], 'http://example.com/dhl/shipment/updateStatus/entity_id/10'],
                ['sales/order/view', ['order_id' => 100], 'http://example.com/sales/order/view/order_id/100'],
                ['ideo_dhl/shipment/delete', ['entity_id' => 10], 'http://example.com/dhl/shipment/delete/entity_id/10'],
            ]);

        $actionColumn = new ShipmentActions(
            $context,
            $uiComponentFactory,
            $urlBuilder,
            [],
            ['name' => 'actions']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 10,
                        'order_id' => 100,
                        'shipment_id' => '123456789',
                    ],
                ],
            ],
        ];

        $result = $actionColumn->prepareDataSource($dataSource);

        $this->assertArrayHasKey('actions', $result['data']['items'][0]);
        $actions = $result['data']['items'][0]['actions'];

        $this->assertEquals('http://example.com/dhl/shipment/edit/entity_id/10', $actions['edit']['href']);
        $this->assertEquals('Details', (string)$actions['edit']['label']);

        $this->assertEquals('http://example.com/dhl/shipment/printLabel/entity_id/10', $actions['print_label']['href']);
        $this->assertEquals('Print Label', (string)$actions['print_label']['label']);

        $this->assertEquals('http://example.com/dhl/shipment/createReturnShipment/entity_id/10', $actions['create_return']['href']);
        $this->assertEquals('Create Return Shipment', (string)$actions['create_return']['label']);

        $this->assertEquals('http://example.com/dhl/shipment/downloadScan/entity_id/10', $actions['download_scan']['href']);
        $this->assertEquals('Download Scan', (string)$actions['download_scan']['label']);

        $this->assertEquals('http://example.com/dhl/shipment/downloadEpod/entity_id/10', $actions['download_epod']['href']);
        $this->assertEquals('Download ePOD', (string)$actions['download_epod']['label']);

        $this->assertEquals('http://example.com/sales/order/view/order_id/100', $actions['view_order']['href']);
        $this->assertEquals('View Order', (string)$actions['view_order']['label']);

        $this->assertEquals('http://example.com/dhl/shipment/delete/entity_id/10', $actions['delete']['href']);
        $this->assertEquals('Delete', (string)$actions['delete']['label']);
    }

    public function testPrepareDataSourceWithoutOrderIdOnlyRendersEditAndDeleteActions(): void
    {
        $context = $this->createStub(ContextInterface::class);
        $uiComponentFactory = $this->createStub(UiComponentFactory::class);
        $urlBuilder = $this->createMock(UrlInterface::class);

        $urlBuilder->expects($this->exactly(7))
            ->method('getUrl')
            ->willReturnMap([
                ['ideo_dhl/shipment/edit', ['entity_id' => 15], 'http://example.com/dhl/shipment/edit/entity_id/15'],
                ['ideo_dhl/shipment/printLabel', ['entity_id' => 15], 'http://example.com/dhl/shipment/printLabel/entity_id/15'],
                ['ideo_dhl/shipment/createReturnShipment', ['entity_id' => 15], 'http://example.com/dhl/shipment/createReturnShipment/entity_id/15'],
                ['ideo_dhl/shipment/downloadScan', ['entity_id' => 15], 'http://example.com/dhl/shipment/downloadScan/entity_id/15'],
                ['ideo_dhl/shipment/downloadEpod', ['entity_id' => 15], 'http://example.com/dhl/shipment/downloadEpod/entity_id/15'],
                ['ideo_dhl/shipment/updateStatus', ['entity_id' => 15], 'http://example.com/dhl/shipment/updateStatus/entity_id/15'],
                ['ideo_dhl/shipment/delete', ['entity_id' => 15], 'http://example.com/dhl/shipment/delete/entity_id/15'],
            ]);

        $actionColumn = new ShipmentActions(
            $context,
            $uiComponentFactory,
            $urlBuilder,
            [],
            ['name' => 'actions']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 15,
                        'shipment_id' => '987654321',
                    ],
                ],
            ],
        ];

        $result = $actionColumn->prepareDataSource($dataSource);

        $actions = $result['data']['items'][0]['actions'];
        $this->assertArrayHasKey('edit', $actions);
        $this->assertArrayHasKey('print_label', $actions);
        $this->assertArrayHasKey('download_scan', $actions);
        $this->assertArrayHasKey('download_epod', $actions);
        $this->assertArrayHasKey('delete', $actions);
        $this->assertArrayNotHasKey('view_order', $actions);
    }

    public function testPrepareDataSourceWithReturnShipmentIdHidesCreateReturnAction(): void
    {
        $context = $this->createStub(ContextInterface::class);
        $uiComponentFactory = $this->createStub(UiComponentFactory::class);
        $urlBuilder = $this->createMock(UrlInterface::class);

        $urlBuilder->expects($this->exactly(6))
            ->method('getUrl')
            ->willReturnMap([
                ['ideo_dhl/shipment/edit', ['entity_id' => 20], 'http://example.com/dhl/shipment/edit/entity_id/20'],
                ['ideo_dhl/shipment/printLabel', ['entity_id' => 20], 'http://example.com/dhl/shipment/printLabel/entity_id/20'],
                ['ideo_dhl/shipment/downloadScan', ['entity_id' => 20], 'http://example.com/dhl/shipment/downloadScan/entity_id/20'],
                ['ideo_dhl/shipment/downloadEpod', ['entity_id' => 20], 'http://example.com/dhl/shipment/downloadEpod/entity_id/20'],
                ['ideo_dhl/shipment/updateStatus', ['entity_id' => 20], 'http://example.com/dhl/shipment/updateStatus/entity_id/20'],
                ['ideo_dhl/shipment/delete', ['entity_id' => 20], 'http://example.com/dhl/shipment/delete/entity_id/20'],
            ]);

        $actionColumn = new ShipmentActions(
            $context,
            $uiComponentFactory,
            $urlBuilder,
            [],
            ['name' => 'actions']
        );

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => 20,
                        'shipment_id' => '123456789',
                        'return_shipment_id' => 'RET987654',
                    ],
                ],
            ],
        ];

        $result = $actionColumn->prepareDataSource($dataSource);

        $actions = $result['data']['items'][0]['actions'];
        $this->assertArrayHasKey('edit', $actions);
        $this->assertArrayNotHasKey('create_return', $actions);
    }
}
