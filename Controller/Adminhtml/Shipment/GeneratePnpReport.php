<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Model\Shipment\PnpReportService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Throwable;

class GeneratePnpReport extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::shipment';

    /**
     * @param Context $context
     * @param PnpReportService $pnpReportService
     * @param FileFactory $fileFactory
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Context $context,
        private readonly PnpReportService $pnpReportService,
        private readonly FileFactory $fileFactory,
        private readonly TimezoneInterface $timezone
    ) {
        parent::__construct($context);
    }

    /**
     * Generate courier report (PnP) download.
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute(): ResponseInterface|ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $pnpData = $this->getRequest()->getParam('pnp');
        $date = is_array($pnpData) ? ($pnpData['date'] ?? '') : (string) $this->getRequest()->getParam('date', '');
        $paramType = $this->getRequest()->getParam(
            'type_package',
            $this->getRequest()->getParam('typePackage', '')
        );
        $typePackage = is_array($pnpData) ? ($pnpData['typePackage'] ?? '') : (string) $paramType;

        if (empty($date)) {
            $date = $this->timezone->scopeDate()->format('Y-m-d');
        }

        if (empty($typePackage)) {
            $typePackage = 'ALL';
        }

        try {
            $reportData = $this->pnpReportService->generateReport($date, $typePackage);

            return $this->fileFactory->create(
                $reportData['filename'],
                [
                    'type' => 'string',
                    'value' => $reportData['content'],
                    'rm' => true,
                ],
                'tmp',
                $reportData['mimeType']
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/index');
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not generate courier report: %1', $e->getMessage())
            );
            return $resultRedirect->setPath('*/*/index');
        }
    }
}
