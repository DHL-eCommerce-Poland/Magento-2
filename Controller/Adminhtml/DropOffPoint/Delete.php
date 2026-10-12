<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\DropOffPoint;

use Exception;
use DhlPoland\Dhl\Api\DropOffPointRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::drop_off_point';

    /**
     * @param Context $context
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     */
    public function __construct(
        Context $context,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Delete Drop-Off Point action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('entity_id');

        if ($id) {
            try {
                $this->dropOffPointRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('You deleted the drop-off point.'));
                return $resultRedirect->setPath('*/*/index');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage(__('Error deleting drop-off point: %1', $e->getMessage()));
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
