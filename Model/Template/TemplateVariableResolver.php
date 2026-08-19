<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Template;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

class TemplateVariableResolver
{
    /**
     * Resolve tags in template text using order data.
     *
     * @param string $templateText
     * @param OrderInterface $order
     *
     * @return string
     */
    public function resolve(string $templateText, OrderInterface $order): string
    {
        if (empty(trim($templateText))) {
            return '';
        }

        $variables = $this->extractVariables($order);

        return strtr($templateText, $variables);
    }

    /**
     * Extract replacement array from order.
     *
     * @param OrderInterface $order
     *
     * @return array
     */
    public function extractVariables(OrderInterface $order): array
    {
        $productNames = [];
        $skus = [];
        $totalQty = 0;

        $items = $order->getItems() ?? [];
        /** @var OrderItemInterface $item */
        foreach ($items as $item) {
            if ($item->getParentItem() !== null) {
                continue;
            }
            if ($item->getName()) {
                $productNames[] = $item->getName();
            }
            if ($item->getSku()) {
                $skus[] = $item->getSku();
            }
            $totalQty += (int)($item->getQtyOrdered() ?? 1);
        }

        $custFirst = (string)($order->getCustomerFirstname() ?? '');
        $custLast = (string)($order->getCustomerLastname() ?? '');
        $customerName = trim(sprintf('%s %s', $custFirst, $custLast));

        if (empty($customerName) && $order->getShippingAddress()) {
            $shipFirst = (string)($order->getShippingAddress()->getFirstname() ?? '');
            $shipLast = (string)($order->getShippingAddress()->getLastname() ?? '');
            $customerName = trim(sprintf('%s %s', $shipFirst, $shipLast));
        }

        $paymentTitle = '';
        if ($order->getPayment()) {
            $paymentInfo = $order->getPayment()->getAdditionalInformation('method_title');
            $paymentTitle = (string)($paymentInfo ?? $order->getPayment()->getMethod() ?? '');
        }

        return [
            '{order_increment_id}' => (string)($order->getIncrementId() ?? ''),
            '{product_names}' => implode(', ', $productNames),
            '{sku_list}' => implode(', ', $skus),
            '{qty_total}' => (string)$totalQty,
            '{customer_name}' => $customerName,
            '{payment_method}' => $paymentTitle,
        ];
    }
}
