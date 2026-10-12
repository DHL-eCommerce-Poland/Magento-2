<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\DeliveryService;

use Exception;
use DhlPoland\Dhl\Api\DeliveryServiceRepositoryInterface;
use DhlPoland\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::delivery_service';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Mass delete delivery services.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deletedCount = 0;

            foreach ($collection as $item) {
                $this->deliveryServiceRepository->delete($item);
                $deletedCount++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 record(s) have been deleted.', $deletedCount)
            );
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Error during mass delete: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
