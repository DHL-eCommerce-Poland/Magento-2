<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\DeliveryService;

use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::delivery_service';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Edit Delivery Service action.
     *
     * @return Page|Redirect
     */
    public function execute(): Page|Redirect
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ideo_Dhl::delivery_service');

        if ($id) {
            try {
                $service = $this->deliveryServiceRepository->getById($id);
                $title = __('Edit Delivery Service "%1"', $service->getTemplateName());
                $resultPage->getConfig()->getTitle()->prepend($title);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This delivery service no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/index');
            }
        } else {
            $resultPage->getConfig()->getTitle()->prepend(__('New Delivery Service'));
        }

        return $resultPage;
    }
}
