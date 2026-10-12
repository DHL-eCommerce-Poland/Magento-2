<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\CustomsTemplate;

use DhlPoland\Dhl\Model\CustomsTemplateRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::customs_template';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param CustomsTemplateRepository $repository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CustomsTemplateRepository $repository
    ) {
        parent::__construct($context);
    }

    /**
     * Edit action.
     *
     * @return Page|Redirect
     */
    public function execute(): Page|Redirect
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('DhlPoland_Dhl::customs_template');

        if ($id) {
            try {
                $template = $this->repository->getById($id);
                $title = __('Edit Customs Clearance Template "%1"', $template->getTemplateName());
                $resultPage->getConfig()->getTitle()->prepend($title);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This customs clearance no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/index');
            }
        } else {
            $resultPage->getConfig()->getTitle()->prepend(__('New Customs Clearance Template'));
        }

        return $resultPage;
    }
}
