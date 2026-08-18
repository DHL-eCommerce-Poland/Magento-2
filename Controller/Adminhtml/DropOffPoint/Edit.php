<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\DropOffPoint;

use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::drop_off_point';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Edit Drop-Off Point action.
     *
     * @return Page|Redirect
     */
    public function execute(): Page|Redirect
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ideo_Dhl::drop_off_point');

        if ($id) {
            try {
                $dropOffPoint = $this->dropOffPointRepository->getById($id);
                $title = __('Edit Drop-Off Point "%1"', $dropOffPoint->getTemplateName());
                $resultPage->getConfig()->getTitle()->prepend($title);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This drop-off point no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/index');
            }
        } else {
            $resultPage->getConfig()->getTitle()->prepend(__('New Drop-Off Point'));
        }

        return $resultPage;
    }
}
