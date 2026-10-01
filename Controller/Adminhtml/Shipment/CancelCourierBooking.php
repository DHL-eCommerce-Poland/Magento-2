<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Model\Shipment\CourierBookingCancellationService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Psr\Log\LoggerInterface;

class CancelCourierBooking extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param CourierBookingCancellationService $cancellationService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly CourierBookingCancellationService $cancellationService,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute courier booking cancellation POST action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $selectedIds = $this->getRequest()->getParam('selected', []);
        $entityId = $this->getRequest()->getParam('entity_id');

        if (!empty($entityId)) {
            $selectedIds[] = $entityId;
        }

        if (empty($selectedIds)) {
            $this->messageManager->addErrorMessage(
                __('Please select at least one shipment to cancel courier booking.')
            );
            return $resultRedirect->setPath('dhlpoland/shipment/index');
        }

        try {
            $result = $this->cancellationService->cancelCourierBooking($selectedIds);

            if ($result->isSuccess()) {
                $this->messageManager->addSuccessMessage(
                    __('Courier booking has been successfully cancelled.')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Failed to cancel courier booking: %1', $result->getErrorMessage())
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                '[CancelCourierBooking] Exception during courier cancellation: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(
                __('An error occurred while cancelling courier booking: %1', $e->getMessage())
            );
        }

        $referer = $this->_redirect->getRefererUrl();
        if (!empty($referer)) {
            return $resultRedirect->setUrl($referer);
        }

        return $resultRedirect->setPath('dhlpoland/shipment/index');
    }
}
