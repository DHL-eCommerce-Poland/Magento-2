<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Model\Shipment\DocumentDownloadService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Throwable;

class DownloadEpod extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param DocumentDownloadService $documentDownloadService
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        private readonly DocumentDownloadService $documentDownloadService,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Download electronic proof of delivery (ePOD).
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
            $epodData = $this->documentDownloadService->getEpodContent($entityId);

            return $this->fileFactory->create(
                $epodData['filename'],
                [
                    'type' => 'string',
                    'value' => $epodData['content'],
                    'rm' => true,
                ],
                'tmp',
                $epodData['mimeType']
            );
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not download ePOD: %1', $e->getMessage())
            );
            return $resultRedirect->setPath('*/*/index');
        }
    }
}
