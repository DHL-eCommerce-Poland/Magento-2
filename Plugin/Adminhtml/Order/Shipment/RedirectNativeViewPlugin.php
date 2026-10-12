<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Plugin\Adminhtml\Order\Shipment;

use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\App\Action as BackendAction;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class RedirectNativeViewPlugin
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param UrlInterface $urlBuilder
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly UrlInterface $urlBuilder,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Redirect to DHL shipment view if native shipment has linked DHL shipment entity.
     *
     * If DHL shipment was deleted, display notice message and proceed to default Magento shipment view.
     *
     * @param BackendAction $subject
     * @param callable $proceed
     *
     * @return mixed
     */
    public function aroundExecute(BackendAction $subject, callable $proceed): mixed
    {
        $magentoShipmentId = (int)($this->request->getParam('shipment_id') ?: $this->request->getParam('id'));
        if ($magentoShipmentId > 0) {
            try {
                $dhlShipment = $this->shipmentDataRepository->getByMagentoShipmentId($magentoShipmentId);
                if ($dhlShipment && $dhlShipment->getEntityId()) {
                    $dhlEditUrl = $this->urlBuilder->getUrl(
                        'dhlpoland/shipment/edit',
                        ['entity_id' => $dhlShipment->getEntityId()]
                    );
                    $this->response->setRedirect($dhlEditUrl);
                    return null;
                }

                if ($this->isDhlShipmentDeleted($magentoShipmentId)) {
                    $this->addUniqueNoticeMessage(
                        __('DHL shipment has been deleted. Redirected to the default Magento shipment view.')
                    );
                }
            } catch (Throwable $exception) {
                $this->logger->error(
                    sprintf(
                        'Error in native shipment view plugin for shipment #%d: %s',
                        $magentoShipmentId,
                        $exception->getMessage()
                    ),
                    ['exception' => $exception]
                );
            }
        }

        return $proceed();
    }

    /**
     * Check if the order for this Magento shipment was placed with DHL shipping.
     *
     * @param int $magentoShipmentId
     *
     * @return bool
     */
    private function isDhlShipmentDeleted(int $magentoShipmentId): bool
    {
        try {
            $shipment = $this->shipmentRepository->get($magentoShipmentId);
            $orderId = (int)$shipment->getOrderId();
            if ($orderId <= 0) {
                return false;
            }

            $order = $this->orderRepository->get($orderId);
            $shippingMethod = (string)$order->getShippingMethod();

            return str_contains(strtolower($shippingMethod), 'dhl');
        } catch (Throwable $e) {
            $this->logger->warning(sprintf(
                'Failed to check shipping method for shipment #%d: %s',
                $magentoShipmentId,
                $e->getMessage()
            ));
        }

        return false;
    }

    /**
     * Add notice message to message manager if not already queued.
     *
     * @param Phrase|string $message
     *
     * @return void
     */
    private function addUniqueNoticeMessage(Phrase|string $message): void
    {
        $text = (string)$message;
        try {
            $messages = $this->messageManager->getMessages()->getItems();
            foreach ($messages as $msg) {
                if ($msg->getText() === $text) {
                    return;
                }
            }
        } catch (Throwable) {
            $messages = [];
        }

        $this->messageManager->addNoticeMessage($message instanceof Phrase ? $message : __($message));
    }
}
