<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\DeliveryService;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::delivery_service';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Delivery Services index action.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ideo_Dhl::delivery_service');
        $resultPage->getConfig()->getTitle()->prepend(__('Delivery Service Templates'));
        return $resultPage;
    }
}
