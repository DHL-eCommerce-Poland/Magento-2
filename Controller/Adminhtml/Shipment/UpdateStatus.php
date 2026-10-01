<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\ShipmentTrackingService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Psr\Log\LoggerInterface;

class UpdateStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentTrackingService $shipmentTrackingService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentTrackingService $shipmentTrackingService,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute status update action for single shipment.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int) $this->getRequest()->getParam('entity_id');
        $orderId = (int) $this->getRequest()->getParam('order_id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid shipment entity ID.'));
            return $this->getRedirectDestination($resultRedirect, $orderId, $entityId);
        }

        try {
            $result = $this->shipmentTrackingService->updateStatus($entityId);

            if ($result->isSuccess()) {
                $statusDescription = $result->getLatestStatusDescription();
                if ($statusDescription !== null) {
                    $msg = __('DHL shipment status updated to: %1', $statusDescription);
                    if ($result->getReceivedBy() !== null && trim($result->getReceivedBy()) !== '') {
                        $msg = __(
                            'DHL shipment status updated to: %1 (Received by: %2)',
                            $statusDescription,
                            $result->getReceivedBy()
                        );
                    }
                    $this->messageManager->addSuccessMessage($msg);
                } else {
                    $this->messageManager->addSuccessMessage(
                        __('DHL status updated successfully (no tracking events registered yet).')
                    );
                }
            } else {
                $errorMsg = $result->getErrorMessage() ?? __('Failed to fetch status from DHL WebAPI2.');
                $this->messageManager->addErrorMessage(__('DHL status update failed: %1', $errorMsg));
            }
        } catch (Exception $e) {
            $this->logger->error('Error updating DHL shipment status: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Failed to update shipment status: %1', $e->getMessage()));
        }

        return $this->getRedirectDestination($resultRedirect, $orderId, $entityId);
    }

    /**
     * Determine redirect target path based on request params.
     *
     * @param Redirect $resultRedirect
     * @param int $orderId
     * @param int $entityId
     *
     * @return Redirect
     */
    private function getRedirectDestination(Redirect $resultRedirect, int $orderId, int $entityId): Redirect
    {
        if ($orderId > 0) {
            return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }

        if ($this->getRequest()->getParam('redirect_to_edit') && $entityId > 0) {
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $entityId]);
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
