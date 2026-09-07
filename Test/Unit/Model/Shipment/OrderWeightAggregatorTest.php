<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\OrderWeightAggregator;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use PHPUnit\Framework\TestCase;

class OrderWeightAggregatorTest extends TestCase
{
    /**
     * @var OrderWeightAggregator
     */
    private $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new OrderWeightAggregator();
    }

    public function testCalculateTotalWeightWithMultipleItems(): void
    {
        $item1 = $this->createStub(OrderItemInterface::class);
        $item1->method('getParentItem')->willReturn(null);
        $item1->method('getWeight')->willReturn(2.5);
        $item1->method('getQtyOrdered')->willReturn(2.0);

        $item2 = $this->createStub(OrderItemInterface::class);
        $item2->method('getParentItem')->willReturn(null);
        $item2->method('getWeight')->willReturn(1.0);
        $item2->method('getQtyOrdered')->willReturn(3.0);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getItems')->willReturn([$item1, $item2]);

        $total = $this->aggregator->calculateTotalWeight($order);

        $this->assertSame(8.0, $total);
    }

    public function testCalculateTotalWeightSkipsChildItems(): void
    {
        $parentItem = $this->createStub(OrderItemInterface::class);
        $parentItem->method('getParentItem')->willReturn(null);
        $parentItem->method('getWeight')->willReturn(3.0);
        $parentItem->method('getQtyOrdered')->willReturn(1.0);

        $childItem = $this->createStub(OrderItemInterface::class);
        $childItem->method('getParentItem')->willReturn($parentItem);
        $childItem->method('getWeight')->willReturn(3.0);
        $childItem->method('getQtyOrdered')->willReturn(1.0);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getItems')->willReturn([$parentItem, $childItem]);

        $total = $this->aggregator->calculateTotalWeight($order);

        $this->assertSame(3.0, $total);
    }

    public function testCalculateTotalWeightFallbackWhenZero(): void
    {
        $item = $this->createStub(OrderItemInterface::class);
        $item->method('getParentItem')->willReturn(null);
        $item->method('getWeight')->willReturn(0.0);
        $item->method('getQtyOrdered')->willReturn(1.0);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getItems')->willReturn([$item]);

        $total = $this->aggregator->calculateTotalWeight($order, 2.0);

        $this->assertSame(2.0, $total);
    }
}
