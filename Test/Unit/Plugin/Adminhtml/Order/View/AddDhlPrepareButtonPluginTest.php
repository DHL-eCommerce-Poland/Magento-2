<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Adminhtml\Order\View;

use Ideo\Dhl\Plugin\Adminhtml\Order\View\AddDhlPrepareButtonPlugin;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderViewBlock;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AddDhlPrepareButtonPluginTest extends TestCase
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var JsonHexTag
     */
    private $serializer;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Ideo\Dhl\Api\ShipmentDataRepositoryInterface
     */
    private $shipmentDataRepository;

    /**
     * @var AddDhlPrepareButtonPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createStub(UrlInterface::class);
        $this->serializer = new JsonHexTag();
        $this->orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $this->shipmentDataRepository = $this->createStub(\Ideo\Dhl\Api\ShipmentDataRepositoryInterface::class);
        $this->plugin = new AddDhlPrepareButtonPlugin(
            $this->urlBuilder,
            $this->serializer,
            $this->orderRepository,
            $this->shipmentDataRepository
        );
    }

    public function testBeforeSetLayoutAddsButtonForDhlShippingMethod(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('dhlparcel_standard');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getStatus')->willReturn('processing');
        $order->method('hasShipments')->willReturn(false);

        $this->orderRepository->method('get')->willReturn($order);
        $this->shipmentDataRepository->method('getByOrderId')->willReturn(null);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(10);
        $block->expects($this->once())
            ->method('addButton')
            ->with(
                'dhlparcel_prepare_shipment',
                self::callback(fn ($arg) => is_array($arg) && isset($arg['label'])),
                0,
                95
            );

        $this->plugin->beforeSetLayout($block);
    }

    public function testBeforeSetLayoutSkipsWhenShipmentAlreadyExists(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('dhlparcel_standard');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);

        $this->orderRepository->method('get')->willReturn($order);
        $existingEntity = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $this->shipmentDataRepository->method('getByOrderId')->willReturn($existingEntity);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(14);
        $block->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($block);
    }

    public function testBeforeSetLayoutAddsButtonForNonDhlShippingMethod(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getStatus')->willReturn('processing');

        $this->orderRepository->method('get')->willReturn($order);
        $this->shipmentDataRepository->method('getByOrderId')->willReturn(null);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(11);
        $block->expects($this->once())
            ->method('addButton')
            ->with(
                'dhlparcel_prepare_shipment',
                self::callback(fn ($arg) => is_array($arg) && isset($arg['label'])),
                0,
                95
            );

        $this->plugin->beforeSetLayout($block);
    }

    public function testBeforeSetLayoutSkipsCanceledOrClosedOrder(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('dhlparcel_standard');
        $order->method('getState')->willReturn(Order::STATE_CANCELED);

        $this->orderRepository->method('get')->willReturn($order);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(12);
        $block->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($block);
    }

    public function testBeforeSetLayoutSkipsCompleteOrder(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('dhlparcel_standard');
        $order->method('getState')->willReturn(Order::STATE_COMPLETE);

        $this->orderRepository->method('get')->willReturn($order);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(13);
        $block->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($block);
    }

    public function testBeforeSetLayoutEncodesPrepareUrlAsJavascriptStringLiteral(): void
    {
        $prepareUrl = 'https://example.test/prepare?value=\'</script>&quote="';
        $this->urlBuilder->method('getUrl')->willReturn($prepareUrl);

        $order = $this->createStub(Order::class);
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $this->orderRepository->method('get')->willReturn($order);
        $this->shipmentDataRepository->method('getByOrderId')->willReturn(null);

        $safeUrl = $this->serializer->serialize($prepareUrl);
        self::assertStringContainsString('\\u003C\\/script\\u003E', $safeUrl);
        self::assertStringNotContainsString('</script>', $safeUrl);
        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(10);
        $block->expects($this->once())
            ->method('addButton')
            ->with(
                'dhlparcel_prepare_shipment',
                $this->callback(static function (array $button) use ($safeUrl): bool {
                    return $button['onclick'] === sprintf(
                        'window.ideoDhlOpenPrepareModal && window.ideoDhlOpenPrepareModal(10, %s)',
                        $safeUrl
                    );
                }),
                0,
                95
            );

        $this->plugin->beforeSetLayout($block);
    }
}
