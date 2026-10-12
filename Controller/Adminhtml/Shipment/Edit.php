<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::shipment';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Edit DHL Shipment action.
     *
     * @return Page|Redirect
     */
    public function execute(): Page|Redirect
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('DhlPoland_Dhl::shipment');

        if ($id) {
            try {
                $shipment = $this->shipmentDataRepository->getById($id);
                $title = __('DHL Shipment #%1', $shipment->getShipmentId() ?? (string)$id);
                $resultPage->getConfig()->getTitle()->prepend($title);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This DHL shipment no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/index');
            }
        } else {
            $resultPage->getConfig()->getTitle()->prepend(__('DHL Shipment Details'));
        }

        return $resultPage;
    }
}
