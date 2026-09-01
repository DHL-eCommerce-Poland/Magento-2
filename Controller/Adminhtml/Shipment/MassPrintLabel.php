<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\LabelPrintingService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassPrintLabel extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param LabelPrintingService $labelPrintingService
     * @param FileFactory $fileFactory
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly LabelPrintingService $labelPrintingService,
        private readonly FileFactory $fileFactory,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute mass label print action for selected grid shipments.
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute(): ResponseInterface|ResultInterface
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $entityIds = [];

            foreach ($collection as $item) {
                $id = (int) $item->getEntityId();
                if ($id > 0) {
                    $entityIds[] = $id;
                }
            }

            if (empty($entityIds)) {
                $selectedIds = (array) $this->getRequest()->getParam('selected', []);
                if (!empty($selectedIds)) {
                    $entityIds = array_map('intval', $selectedIds);
                }
            }

            if (empty($entityIds)) {
                $this->messageManager->addErrorMessage(__('No shipments selected for label printing.'));
                return $this->_redirect('*/*/index');
            }

            $labelData = $this->labelPrintingService->getLabelsContent($entityIds);

            return $this->fileFactory->create(
                $labelData['filename'],
                [
                    'type' => 'string',
                    'value' => $labelData['content'],
                    'rm' => true,
                ],
                'tmp',
                $labelData['mimeType']
            );
        } catch (Exception $e) {
            $this->logger?->error('[MassPrintLabel] Error: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                __('Could not print labels: %1', $e->getMessage())
            );
            return $this->_redirect('*/*/index');
        }
    }
}
