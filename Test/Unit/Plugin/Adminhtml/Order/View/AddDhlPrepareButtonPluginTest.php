<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Adminhtml\Order\View;

use Ideo\Dhl\Plugin\Adminhtml\Order\View\AddDhlPrepareButtonPlugin;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderViewBlock;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class AddDhlPrepareButtonPluginTest extends TestCase
{
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var AddDhlPrepareButtonPlugin
     */
    private AddDhlPrepareButtonPlugin $plugin;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createStub(UrlInterface::class);
        $this->orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $this->plugin = new AddDhlPrepareButtonPlugin($this->urlBuilder, $this->orderRepository);
    }

    public function testBeforeSetLayoutAddsButtonForDhlShippingMethod(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('dhlparcel_standard');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getStatus')->willReturn('processing');
        $order->method('hasShipments')->willReturn(false);

        $this->orderRepository->method('get')->willReturn($order);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(10);
        $block->expects($this->once())
            ->method('addButton')
            ->with(
                'dhlparcel_prepare_shipment',
                $this->callback(fn($arg) => is_array($arg) && isset($arg['label'])),
                0,
                95
            );

        $this->plugin->beforeSetLayout($block);
    }

    public function testBeforeSetLayoutSkipsNonDhlShippingMethod(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('flatrate_flatrate');

        $this->orderRepository->method('get')->willReturn($order);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(11);
        $block->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($block);
    }

    public function testBeforeSetLayoutSkipsShippedOrCompleteOrder(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('dhlparcel_standard');
        $order->method('getState')->willReturn(Order::STATE_COMPLETE);
        $order->method('getStatus')->willReturn('shipped');

        $this->orderRepository->method('get')->willReturn($order);

        $block = $this->createMock(OrderViewBlock::class);
        $block->method('getOrderId')->willReturn(12);
        $block->expects($this->never())->method('addButton');

        $this->plugin->beforeSetLayout($block);
    }
}
