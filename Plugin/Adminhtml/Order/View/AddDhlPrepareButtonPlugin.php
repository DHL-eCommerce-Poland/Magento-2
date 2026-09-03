<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Adminhtml\Order\View;

use Exception;
use Magento\Backend\Model\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderViewBlock;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Plugin adding "DHL Shipment" button to admin order view page for DHL Parcel orders.
 */
class AddDhlPrepareButtonPlugin
{
    /**
     * @param UrlInterface $urlBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Add DHL shipment button before layout is rendered.
     *
     * @param OrderViewBlock $subject
     *
     * @return void
     */
    public function beforeSetLayout(OrderViewBlock $subject): void
    {
        $orderId = $subject->getOrderId();
        if (!$orderId) {
            return;
        }

        try {
            /** @var Order $order */
            $order = $this->orderRepository->get((int)$orderId);

            // Hide button for virtual/downloadable orders
            if ($order->getIsVirtual()) {
                return;
            }

            $shippingMethod = strtolower((string)($order->getShippingMethod() ?? ''));

            $isDhlMethod = str_starts_with($shippingMethod, 'dhlparcel') || str_starts_with($shippingMethod, 'dhl');
            if (empty($shippingMethod) || !$isDhlMethod) {
                return;
            }

            // Hide button if order is canceled or closed
            $state = $order->getState();

            if ($state === Order::STATE_CLOSED || $state === Order::STATE_CANCELED) {
                return;
            }

            $prepareUrl = $this->urlBuilder->getUrl('dhlparcel/shipment/prepare', ['order_id' => $orderId]);
            $onClickJs = sprintf(
                'window.ideoDhlOpenPrepareModal && window.ideoDhlOpenPrepareModal(%d, "%s")',
                $orderId,
                $prepareUrl
            );

            $subject->addButton(
                'dhlparcel_prepare_shipment',
                [
                    'label' => __('DHL Shipment'),
                    'class' => 'action-secondary dhl-prepare-shipment-btn',
                    'onclick' => $onClickJs,
                ],
                0,
                95
            );
        } catch (Exception $e) {
            $this->logger?->error('Error adding DHL prepare shipment button: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
