<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Template;

use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\TestCase;

class TemplateVariableResolverTest extends TestCase
{
    /**
     * @var TemplateVariableResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TemplateVariableResolver();
    }

    public function testResolveVariables(): void
    {
        $item1 = $this->createStub(OrderItemInterface::class);
        $item1->method('getParentItem')->willReturn(null);
        $item1->method('getName')->willReturn('Laptop Dell');
        $item1->method('getSku')->willReturn('DELL-123');
        $item1->method('getQtyOrdered')->willReturn(1.0);

        $item2 = $this->createStub(OrderItemInterface::class);
        $item2->method('getParentItem')->willReturn(null);
        $item2->method('getName')->willReturn('Myszka USB');
        $item2->method('getSku')->willReturn('MOUSE-999');
        $item2->method('getQtyOrdered')->willReturn(2.0);

        $payment = $this->createStub(OrderPaymentInterface::class);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['method_title', 'Płatność za pobraniem']
        ]);

        $order = $this->createStub(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('100001234');
        $order->method('getCustomerFirstname')->willReturn('Jan');
        $order->method('getCustomerLastname')->willReturn('Kowalski');
        $order->method('getItems')->willReturn([$item1, $item2]);
        $order->method('getPayment')->willReturn($payment);

        $template = 'Zamówienie {order_increment_id} dla {customer_name}: '
            . '{product_names} (SKU: {sku_list}, sztuk: {qty_total}) - {payment_method}';
        $result = $this->resolver->resolve($template, $order);

        $expected = 'Zamówienie 100001234 dla Jan Kowalski: Laptop Dell, Myszka USB '
            . '(SKU: DELL-123, MOUSE-999, sztuk: 3) - Płatność za pobraniem';
        $this->assertSame($expected, $result);
    }
}
