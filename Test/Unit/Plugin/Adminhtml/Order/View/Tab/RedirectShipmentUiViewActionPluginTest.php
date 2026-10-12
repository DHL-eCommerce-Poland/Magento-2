<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Plugin\Adminhtml\Order\View\Tab;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Plugin\Adminhtml\Order\View\Tab\RedirectShipmentUiViewActionPlugin;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Ui\Component\Listing\Column\ViewAction;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class RedirectShipmentUiViewActionPluginTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $shipmentDataRepository;
    /**
     * @var MockObject
     */
    private $urlBuilder;
    /**
     * @var MockObject
     */
    private $logger;
    /**
     * @var RedirectShipmentUiViewActionPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->plugin = new RedirectShipmentUiViewActionPlugin(
            $this->shipmentDataRepository,
            $this->urlBuilder,
            $this->logger
        );
    }

    public function testAfterPrepareDataSourceIgnoresNonShipmentViewActions(): void
    {
        $viewAction = $this->createMock(ViewAction::class);
        $viewAction->method('getData')->willReturnCallback(function ($key) {
            return match ($key) {
                'config/viewUrlPath' => 'sales/order/view',
                'name' => 'actions',
                default => null,
            };
        });

        $dataSource = [
            'data' => [
                'items' => [
                    ['entity_id' => '3', 'actions' => ['view' => ['href' => 'http://example.com/native']]]
                ]
            ]
        ];

        $result = $this->plugin->afterPrepareDataSource($viewAction, $dataSource);

        $this->assertEquals('http://example.com/native', $result['data']['items'][0]['actions']['view']['href']);
    }

    public function testAfterPrepareDataSourceUpdatesHrefWhenDhlShipmentExists(): void
    {
        $viewAction = $this->createMock(ViewAction::class);
        $viewAction->method('getData')->willReturnCallback(function ($key) {
            return match ($key) {
                'config/viewUrlPath' => 'adminhtml/order_shipment/view',
                'name' => 'actions',
                default => null,
            };
        });

        $dhlShipment = $this->createMock(ShipmentDataInterface::class);
        $dhlShipment->method('getEntityId')->willReturn(5);
        $dhlShipment->method('getMagentoShipmentId')->willReturn(3);

        $this->shipmentDataRepository->expects($this->once())
            ->method('getListByMagentoShipmentIds')
            ->with([3, 4])
            ->willReturn([$dhlShipment]);

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('dhlpoland/shipment/edit', ['entity_id' => 5])
            ->willReturn('http://example.com/admin/dhlpoland/shipment/edit/entity_id/5');

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => '3',
                        'order_id' => 7,
                        'actions' => ['view' => ['href' => 'http://example.com/native']],
                    ],
                    [
                        'entity_id' => '4',
                        'order_id' => 7,
                        'actions' => ['view' => ['href' => 'http://example.com/native/4']],
                    ],
                ]
            ]
        ];

        $result = $this->plugin->afterPrepareDataSource($viewAction, $dataSource);

        $this->assertEquals(
            'http://example.com/admin/dhlpoland/shipment/edit/entity_id/5',
            $result['data']['items'][0]['actions']['view']['href']
        );
        $this->assertSame(
            'http://example.com/native/4',
            $result['data']['items'][1]['actions']['view']['href']
        );
    }

    public function testAfterPrepareDataSourceUsesNewestDhlRecordForDuplicateShipmentLinks(): void
    {
        $viewAction = $this->createMock(ViewAction::class);
        $viewAction->method('getData')->willReturnCallback(function ($key) {
            return match ($key) {
                'config/viewUrlPath' => 'adminhtml/order_shipment/view',
                'name' => 'actions',
                default => null,
            };
        });

        $newestShipment = $this->createMock(ShipmentDataInterface::class);
        $newestShipment->method('getMagentoShipmentId')->willReturn(3);
        $newestShipment->method('getEntityId')->willReturn(9);
        $olderShipment = $this->createMock(ShipmentDataInterface::class);
        $olderShipment->method('getMagentoShipmentId')->willReturn(3);
        $olderShipment->method('getEntityId')->willReturn(5);

        $this->shipmentDataRepository->expects($this->once())
            ->method('getListByMagentoShipmentIds')
            ->with([3])
            ->willReturn([$newestShipment, $olderShipment]);
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('dhlpoland/shipment/edit', ['entity_id' => 9])
            ->willReturn('http://example.com/admin/dhlpoland/shipment/edit/entity_id/9');

        $dataSource = [
            'data' => [
                'items' => [
                    [
                        'entity_id' => '3',
                        'order_id' => 7,
                        'actions' => ['view' => ['href' => 'http://example.com/native']],
                    ],
                ],
            ],
        ];

        $result = $this->plugin->afterPrepareDataSource($viewAction, $dataSource);

        $this->assertSame(
            'http://example.com/admin/dhlpoland/shipment/edit/entity_id/9',
            $result['data']['items'][0]['actions']['view']['href']
        );
    }

    public function testAfterPrepareDataSourceDoesNotQueryRepositoryWithoutShipmentIds(): void
    {
        $viewAction = $this->createMock(ViewAction::class);
        $viewAction->method('getData')->willReturnCallback(function ($key) {
            return match ($key) {
                'config/viewUrlPath' => 'adminhtml/order_shipment/view',
                'name' => 'actions',
                default => null,
            };
        });

        $this->shipmentDataRepository->expects($this->never())->method('getListByMagentoShipmentIds');

        $dataSource = ['data' => ['items' => [['entity_id' => '0']]]];
        $result = $this->plugin->afterPrepareDataSource($viewAction, $dataSource);

        $this->assertSame($dataSource, $result);
    }
}
