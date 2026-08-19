<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Magento\Sales\Api\Data\OrderInterface;

class OrderWeightAggregator
{
    /**
     * Calculate total weight for the given order.
     *
     * @param OrderInterface $order
     * @param float $defaultWeight Fallback weight if calculated total is 0
     *
     * @return float
     */
    public function calculateTotalWeight(OrderInterface $order, float $defaultWeight = 1.0): float
    {
        $items = $order->getItems();
        if (empty($items)) {
            return max($defaultWeight, 0.1);
        }

        $totalWeight = 0.0;
        foreach ($items as $item) {
            // Ignore parent items for configurable/bundle if item has parent
            if ($item->getParentItem() !== null) {
                continue;
            }

            $weight = (float)$item->getWeight();
            $qty = (float)($item->getQtyOrdered() ?? 1);

            if ($weight > 0) {
                $totalWeight += ($weight * $qty);
            }
        }

        return $totalWeight > 0 ? round($totalWeight, 2) : max($defaultWeight, 0.1);
    }
}
