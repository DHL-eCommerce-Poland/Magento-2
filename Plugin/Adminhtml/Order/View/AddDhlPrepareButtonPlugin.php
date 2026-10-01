<?php

declare(strict_types=1);

namespace Ideo\Dhl\Plugin\Adminhtml\Order\View;

use Exception;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderViewBlock;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Plugin adding "DHL Shipment" button to admin order view page.
 */
class AddDhlPrepareButtonPlugin
{
    /**
     * @param UrlInterface $urlBuilder
     * @param JsonHexTag $serializer
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly JsonHexTag $serializer,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
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
            // Hide button if DHL shipment was already created for this order
            if ($this->shipmentDataRepository->getByOrderId((int)$orderId) !== null) {
                return;
            }

            /** @var Order $order */
            $order = $this->orderRepository->get((int)$orderId);

            // Hide button for virtual/downloadable orders
            if ($order->getIsVirtual()) {
                return;
            }

            // Hide button if order is canceled, closed or complete
            $state = $order->getState();

            if ($state === Order::STATE_CLOSED
                || $state === Order::STATE_CANCELED
                || $state === Order::STATE_COMPLETE
            ) {
                return;
            }

            $prepareUrl = $this->urlBuilder->getUrl('dhlpoland/shipment/prepare', ['order_id' => $orderId]);
            $prepareUrlLiteral = $this->serializer->serialize($prepareUrl);
            $onClickJs = sprintf(
                'window.ideoDhlOpenPrepareModal && window.ideoDhlOpenPrepareModal(%d, %s)',
                $orderId,
                $prepareUrlLiteral
            );

            $subject->addButton(
                'dhlpoland_prepare_shipment',
                [
                    'label' => __('DHL Shipment'),
                    'class' => 'action-secondary dhl-prepare-shipment-btn',
                    'onclick' => $onClickJs,
                ],
                0,
                95
            );
        } catch (Exception $e) {
            $this->logger->error('Error adding DHL prepare shipment button: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
