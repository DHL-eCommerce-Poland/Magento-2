<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\CustomsTemplate;

use Exception;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::customs_template';

    /**
     * @param Context $context
     * @param CustomsTemplateRepository $repository
     */
    public function __construct(
        Context $context,
        private readonly CustomsTemplateRepository $repository
    ) {
        parent::__construct($context);
    }

    /**
     * Delete action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('entity_id');

        if ($id) {
            try {
                $this->repository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('You deleted the Customs Clearance.'));
                return $resultRedirect->setPath('*/*/index');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }

        $this->messageManager->addErrorMessage(__('We can not find a Customs Clearance to delete.'));
        return $resultRedirect->setPath('*/*/index');
    }
}
