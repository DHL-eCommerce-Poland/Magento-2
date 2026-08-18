<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\DropOffPoint;

use Ideo\Dhl\Api\Data\DropOffPointInterfaceFactory;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Model\DropOffPoint;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::drop_off_point';

    /**
     * @param Context $context
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     * @param DropOffPointInterfaceFactory $dropOffPointFactory
     */
    public function __construct(
        Context $context,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository,
        private readonly DropOffPointInterfaceFactory $dropOffPointFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Save Drop-Off Point action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            $id = (int)($this->getRequest()->getParam('entity_id') ?: ($data['entity_id'] ?? 0));
            if (empty($data['entity_id'])) {
                unset($data['entity_id']);
            }

            try {
                if ($id) {
                    $model = $this->dropOffPointRepository->getById($id);
                } else {
                    $model = $this->dropOffPointFactory->create();
                }

                /** @var DropOffPoint $model */
                $model->setData($data);
                $this->dropOffPointRepository->save($model);

                $this->messageManager->addSuccessMessage(__('You saved the drop-off point.'));
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getEntityId()]);
                }
                return $resultRedirect->setPath('*/*/index');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error saving drop-off point: %1', $e->getMessage()));
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
