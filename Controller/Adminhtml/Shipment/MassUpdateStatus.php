<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\ShipmentTrackingService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassUpdateStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ShipmentTrackingService $shipmentTrackingService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ShipmentTrackingService $shipmentTrackingService,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute mass status update action for selected shipments.
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
                $this->messageManager->addErrorMessage(__('No shipments selected for status update.'));
                return $resultRedirect->setPath('*/*/index');
            }

            $summary = $this->shipmentTrackingService->massUpdateStatus($entityIds);
            $successCount = count($summary['success']);
            $errorCount = count($summary['errors']);

            if ($successCount > 0) {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 shipment(s) status updated successfully.', $successCount)
                );
            }

            if ($errorCount > 0) {
                $this->messageManager->addErrorMessage(
                    __('Failed to update status for %1 shipment(s). Please check logs.', $errorCount)
                );
            }
        } catch (Exception $e) {
            $this->logger?->error('Error during mass status update: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Error during status update: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
