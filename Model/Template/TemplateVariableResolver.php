<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Template;

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
        $itemIds = [];
        $quoteItemIds = [];
        $productIds = [];
        $skus = [];
        $names = [];
        $descriptions = [];

        $items = $order->getItems() ?? [];
        /** @var OrderItemInterface $item */
        foreach ($items as $item) {
            if ($item->getParentItem() !== null) {
                continue;
            }
            if ($item->getItemId() !== null && (string)$item->getItemId() !== '') {
                $itemIds[] = (string)$item->getItemId();
            }
            if ($item->getQuoteItemId() !== null && (string)$item->getQuoteItemId() !== '') {
                $quoteItemIds[] = (string)$item->getQuoteItemId();
            }
            if ($item->getProductId() !== null && (string)$item->getProductId() !== '') {
                $productIds[] = (string)$item->getProductId();
            }
            if ($item->getSku() !== null && trim((string)$item->getSku()) !== '') {
                $skus[] = trim((string)$item->getSku());
            }
            if ($item->getName() !== null && trim((string)$item->getName()) !== '') {
                $names[] = trim((string)$item->getName());
            }
            if ($item->getDescription() !== null && trim((string)$item->getDescription()) !== '') {
                $descriptions[] = trim((string)$item->getDescription());
            }
        }

        return [
            '{item_id}' => implode(', ', $itemIds),
            '{order_id}' => (string)($order->getEntityId() ?? ''),
            '{quote_item_id}' => implode(', ', $quoteItemIds),
            '{product_id}' => implode(', ', $productIds),
            '{sku}' => implode(', ', $skus),
            '{product_name}' => implode(', ', $names),
            '{order_description}' => implode(', ', $descriptions),
        ];
    }
}
