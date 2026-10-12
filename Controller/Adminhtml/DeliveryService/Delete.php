<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\DeliveryService;

use Exception;
use DhlPoland\Dhl\Api\DeliveryServiceRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::delivery_service';

    /**
     * @param Context $context
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     */
    public function __construct(
        Context $context,
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Delete Delivery Service action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('entity_id');

        if ($id) {
            try {
                $this->deliveryServiceRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('You deleted the delivery service.'));
                return $resultRedirect->setPath('*/*/index');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage(__('Error deleting delivery service: %1', $e->getMessage()));
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
