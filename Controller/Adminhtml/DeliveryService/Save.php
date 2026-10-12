<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\DeliveryService;

use Exception;
use DhlPoland\Dhl\Api\Data\DeliveryServiceInterfaceFactory;
use DhlPoland\Dhl\Api\DeliveryServiceRepositoryInterface;
use DhlPoland\Dhl\Model\DeliveryService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::delivery_service';

    /**
     * @param Context $context
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     * @param DeliveryServiceInterfaceFactory $deliveryServiceFactory
     */
    public function __construct(
        Context $context,
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository,
        private readonly DeliveryServiceInterfaceFactory $deliveryServiceFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Save Delivery Service action.
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
                    $model = $this->deliveryServiceRepository->getById($id);
                } else {
                    $model = $this->deliveryServiceFactory->create();
                }

                if (isset($data['method_code']) && is_array($data['method_code'])) {
                    $data['method_code'] = implode(',', array_filter($data['method_code']));
                }

                if (!isset($data['packages'])) {
                    $data['packages'] = null;
                }
                unset($data['dimensions']);

                /** @var DeliveryService $model */
                $model->setData($data);
                $this->deliveryServiceRepository->save($model);

                $this->messageManager->addSuccessMessage(__('You saved the delivery service.'));
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getEntityId()]);
                }
                return $resultRedirect->setPath('*/*/index');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage(__('Error saving delivery service: %1', $e->getMessage()));
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
