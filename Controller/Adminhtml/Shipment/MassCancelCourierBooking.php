<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\CourierBookingCancellationService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;
use Throwable;

class MassCancelCourierBooking extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param CourierBookingCancellationService $cancellationService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CourierBookingCancellationService $cancellationService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Execute mass courier booking cancellation action for selected shipments.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $entityIds = [];

            foreach ($collection as $item) {
                $id = (int) $item->getEntityId();
                if ($id > 0) {
                    $entityIds[] = $id;
                }
            }

            if (empty($entityIds)) {
                $this->messageManager->addErrorMessage(
                    __('Please select at least one shipment to cancel courier booking.')
                );
                return $resultRedirect->setPath('*/*/index');
            }

            $result = $this->cancellationService->cancelCourierBooking($entityIds);

            if ($result->isSuccess()) {
                $this->messageManager->addSuccessMessage(
                    __('Courier booking has been successfully cancelled.')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Failed to cancel courier booking: %1', $result->getErrorMessage())
                );
            }
        } catch (Throwable $e) {
            $this->logger->error(
                '[MassCancelCourierBooking] Exception during mass courier cancellation: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(
                __('An error occurred while cancelling courier booking: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
