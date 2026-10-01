<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\ShipmentDeletionService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class Delete extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentDeletionService $shipmentDeletionService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDeletionService $shipmentDeletionService,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute single shipment deletion action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int)$this->getRequest()->getParam('entity_id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid shipment entity ID.'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $this->shipmentDeletionService->deleteShipment($entityId);
            $this->messageManager->addSuccessMessage(__('Shipment has been deleted successfully.'));
        } catch (LocalizedException $e) {
            $this->logger->error('Error deleting DHL shipment: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->logger->error('Error deleting DHL shipment: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Failed to delete shipment: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
