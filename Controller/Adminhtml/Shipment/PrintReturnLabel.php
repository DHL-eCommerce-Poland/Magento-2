<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Model\Shipment\LabelPrintingService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Throwable;

class PrintReturnLabel extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param LabelPrintingService $labelPrintingService
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        private readonly LabelPrintingService $labelPrintingService,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Print single shipment return label.
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute(): ResponseInterface|ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int) $this->getRequest()->getParam('entity_id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid shipment entity ID.'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $labelData = $this->labelPrintingService->getReturnLabelContent([$entityId]);

            return $this->fileFactory->create(
                $labelData['filename'],
                [
                    'type' => 'string',
                    'value' => $labelData['content'],
                ],
                'var',
                $labelData['mimeType']
            );
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not print return label: %1', $e->getMessage())
            );
            return $resultRedirect->setPath('*/*/index');
        }
    }
}
