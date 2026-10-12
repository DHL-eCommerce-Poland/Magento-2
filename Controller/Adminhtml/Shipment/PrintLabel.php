<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Model\Shipment\LabelPrintingService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Throwable;

class PrintLabel extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::shipment';

    /**
     * @param Context $context
     * @param LabelPrintingService $labelPrintingService
     * @param FileFactory $fileFactory
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly LabelPrintingService $labelPrintingService,
        private readonly FileFactory $fileFactory,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Print single shipment label.
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute(): ResponseInterface|ResultInterface
    {
        $request = $this->getRequest();
        $isAjax = $request->isXmlHttpRequest() || (bool) $request->getParam('isAjax');
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int) $request->getParam('entity_id');

        if ($entityId <= 0) {
            $msg = __('Invalid shipment entity ID.');
            if ($isAjax) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) $msg,
                ]);
            }
            $this->messageManager->addErrorMessage($msg);
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $labelData = $this->labelPrintingService->getLabelsContent([$entityId]);

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
        } catch (Throwable $e) {
            $errorMsg = __('Could not print label: %1', $e->getMessage());
            if ($isAjax) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) $errorMsg,
                ]);
            }
            $this->messageManager->addErrorMessage($errorMsg);
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $entityId]);
        }
    }
}
