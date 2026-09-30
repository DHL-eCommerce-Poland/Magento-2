<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Template;

use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use PHPUnit\Framework\TestCase;

class TemplateVariableResolverTest extends TestCase
{
    /**
     * @var TemplateVariableResolver
     */
    private TemplateVariableResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TemplateVariableResolver();
    }

    public function testResolveAllSevenVariables(): void
    {
        $parentItem = $this->createStub(OrderItemInterface::class);
        $parentItem->method('getParentItem')->willReturn(null);
        $parentItem->method('getItemId')->willReturn(1);

        $childItem1 = $this->createStub(OrderItemInterface::class);
        $childItem1->method('getParentItem')->willReturn($parentItem);
        $childItem1->method('getItemId')->willReturn(2);

        $item1 = $this->createStub(OrderItemInterface::class);
        $item1->method('getParentItem')->willReturn(null);
        $item1->method('getItemId')->willReturn(10);
        $item1->method('getQuoteItemId')->willReturn(100);
        $item1->method('getProductId')->willReturn(50);
        $item1->method('getSku')->willReturn('SKU-LAPTOP');
        $item1->method('getName')->willReturn('Laptop Dell');
        $item1->method('getDescription')->willReturn('Opis laptopa');

        $item2 = $this->createStub(OrderItemInterface::class);
        $item2->method('getParentItem')->willReturn(null);
        $item2->method('getItemId')->willReturn(11);
        $item2->method('getQuoteItemId')->willReturn(101);
        $item2->method('getProductId')->willReturn(51);
        $item2->method('getSku')->willReturn('SKU-MOUSE');
        $item2->method('getName')->willReturn('Myszka USB');
        $item2->method('getDescription')->willReturn('Opis myszki');

        $order = $this->createStub(OrderInterface::class);
        $order->method('getEntityId')->willReturn(999);
        $order->method('getItems')->willReturn([$childItem1, $item1, $item2]);

        $template = 'Order: {order_id}, Items: {item_id}, Quotes: {quote_item_id}, '
            . 'Products: {product_id}, SKUs: {sku}, Names: {product_name}, Descriptions: {order_description}';

        $result = $this->resolver->resolve($template, $order);

        $expected = 'Order: 999, Items: 10, 11, Quotes: 100, 101, '
            . 'Products: 50, 51, SKUs: SKU-LAPTOP, SKU-MOUSE, '
            . 'Names: Laptop Dell, Myszka USB, Descriptions: Opis laptopa, Opis myszki';

        $this->assertSame($expected, $result);
    }

    public function testResolveEmptyTemplate(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $this->assertSame('', $this->resolver->resolve('   ', $order));
    }
}
