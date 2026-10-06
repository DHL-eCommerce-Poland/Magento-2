<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\ShipmentDeletionService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ShipmentDeletionService $shipmentDeletionService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ShipmentDeletionService $shipmentDeletionService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Mass delete shipments action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deletedCount = 0;
            $errorCount = 0;
            $errorMessages = [];

            foreach ($collection as $item) {
                $entityId = (int)$item->getEntityId();
                if ($entityId <= 0) {
                    continue;
                }

                try {
                    $deletionResult = $this->shipmentDeletionService->deleteShipment($entityId);
                    if ($deletionResult->isSuccess()) {
                        $deletedCount++;
                    } else {
                        $errorCount++;
                        $errorMessages[] = (string) __('Shipment #%1: %2',
                            $entityId,
                            $deletionResult->getErrorMessage() ?: (string) __('Shipment could not be deleted.')
                        );
                    }
                } catch (LocalizedException $e) {
                    $errorCount++;
                    $errorMessages[] = (string) __('Shipment #%1: %2', $entityId, $e->getMessage());
                    $this->logger->error(
                        sprintf('Error deleting shipment #%d during mass delete: %s', $entityId, $e->getMessage()),
                        ['exception' => $e]
                    );
                } catch (Exception $e) {
                    $errorCount++;
                    $errorMessages[] = (string) __(
                        'Shipment #%1: %2',
                        $entityId,
                        (string) __('An unexpected error occurred.')
                    );
                    $this->logger->error(
                        sprintf('Error deleting shipment #%d during mass delete: %s', $entityId, $e->getMessage()),
                        ['exception' => $e]
                    );
                }
            }

            if ($deletedCount > 0) {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 shipment(s) have been deleted.', $deletedCount)
                );
            }

            if ($errorCount > 0) {
                $this->messageManager->addErrorMessage(
                    __(
                        'Failed to delete shipments (%1). Details: %2',
                        $errorCount,
                        implode('; ', $errorMessages)
                    )
                );
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Error during mass delete: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
