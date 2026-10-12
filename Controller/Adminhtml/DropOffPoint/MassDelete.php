<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\DropOffPoint;

use Exception;
use DhlPoland\Dhl\Api\DropOffPointRepositoryInterface;
use DhlPoland\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::drop_off_point';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Mass delete drop-off points.
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
                $this->dropOffPointRepository->delete($item);
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
