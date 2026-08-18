<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\DropOffPoint;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::drop_off_point';

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
     * Drop-Off Points index action.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ideo_Dhl::drop_off_point');
        $resultPage->getConfig()->getTitle()->prepend(__('DHL Drop-Off Points'));
        return $resultPage;
    }
}
