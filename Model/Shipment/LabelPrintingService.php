<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong
// phpcs:disable Generic.Metrics.NestingLevel.TooHigh

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Psr\Log\LoggerInterface;
use Zend_Pdf_Exception;
use ZipArchive;

class LabelPrintingService
{
    private const XML_PATH_DEFAULT_LABEL_TYPE = 'carriers/dhlpoland/label_type';

    /**
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param LabelGenerator $labelGenerator
     * @param FileDriver $fileDriver
     * @param CustomsDocumentService $customsDocumentService
     */
    public function __construct(
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly LabelGenerator $labelGenerator,
        private readonly FileDriver $fileDriver,
        private readonly CustomsDocumentService $customsDocumentService
    ) {
    }

    /**
     * Get label PDF/binary content for given shipment entity IDs.
     *
     * @param int[] $entityIds Array of Magento DHL shipment entity IDs
     * @param string|null $overrideLabelType Optional explicit label type override
     *
     * @return array
     * @throws LocalizedException|Zend_Pdf_Exception
     */
    public function getLabelsContent(array $entityIds, ?string $overrideLabelType = null): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (empty($entityIds)) {
            throw new LocalizedException(__('No shipments selected for label printing.'));
        }

        $defaultConfigLabelType = (string) $this->scopeConfig->getValue(self::XML_PATH_DEFAULT_LABEL_TYPE) ?: 'BLP';
        $itemsToPrint = [];
        $entityIdByShipmentId = [];

        foreach ($entityIds as $id) {
            try {
                $shipment = $this->shipmentDataRepository->getById($id);
                $dhlShipmentId = $shipment->getShipmentId();
                if (empty($dhlShipmentId)) {
                    continue;
                }

                $shipmentIdStr = (string) $dhlShipmentId;
                $entityIdByShipmentId[$shipmentIdStr] = (int) $id;

                $labelType = $overrideLabelType;
                if (empty($labelType)) {
                    $shipmentSettings = $this->getShipmentSettings($shipment);
                    $labelType = !empty($shipmentSettings['labelType'])
                        ? (string) $shipmentSettings['labelType']
                        : null;
                }

                $itemsToPrint[] = [
                    'shipmentId' => $shipmentIdStr,
                    'labelType' => $labelType ?: $defaultConfigLabelType,
                ];
            } catch (Throwable $e) {
                $this->logger->warning(sprintf(
                    '[LabelPrintingService] Could not load shipment entity #%d: %s',
                    $id,
                    $e->getMessage()
                ));
            }
        }

        if (empty($itemsToPrint)) {
            throw new LocalizedException(
                __('None of the selected shipments have valid DHL shipment IDs.')
            );
        }

        $credentials = $this->credentialsProvider->getCredentials();

        $result = $this->client->getLabels($credentials, $itemsToPrint);

        if (!$result->isSuccess()) {
            throw new LocalizedException(
                __('Failed to retrieve labels from DHL: %1', $result->getErrorMessage() ?? __('Unknown API error'))
            );
        }

        $items = $result->getItems();
        if (empty($items)) {
            throw new LocalizedException(__('No label data returned by DHL.'));
        }

        $customsDocsByEntityId = $this->customsDocumentService->getDocumentsForShipments($entityIds);

        if (count($items) === 1) {
            $single = $items[0];
            $shipmentId = (string) $single['shipmentId'];
            $entityId = $entityIdByShipmentId[$shipmentId] ?? null;
            $docs = $entityId ? ($customsDocsByEntityId[$entityId] ?? []) : [];

            if (empty($docs)) {
                $mime = strtolower((string) ($single['mimeType'] ?? ''));
                $ext = str_contains($mime, 'pdf') ? 'pdf' : (str_contains($mime, 'zpl') ? 'zpl' : 'txt');
                return [
                    'content' => $single['labelData'],
                    'mimeType' => $single['mimeType'],
                    'filename' => sprintf('dhl_label_%s.%s', $single['shipmentId'], $ext),
                ];
            }

            $isPdf = str_contains(strtolower((string) ($single['mimeType'] ?? '')), 'pdf');
            if ($isPdf) {
                foreach ($docs as $doc) {
                    if (!str_contains(strtolower((string) ($doc['mime_type'] ?? '')), 'pdf')) {
                        $isPdf = false;
                        break;
                    }
                }
            }

            if ($isPdf) {
                $pdfStrings = array_merge([$single['labelData']], array_column($docs, 'content'));
                return [
                    'content' => $this->mergePdfDocuments($pdfStrings),
                    'mimeType' => 'application/pdf',
                    'filename' => sprintf('dhl_label_%s.pdf', $shipmentId),
                ];
            }

            $files = [];
            $mime = strtolower((string) ($single['mimeType'] ?? ''));
            $ext = str_contains($mime, 'pdf') ? 'pdf' : (str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'zpl') ? 'zpl' : 'txt'));
            $files[] = [
                'filename' => sprintf('dhl_label_%s.%s', $shipmentId, $ext),
                'content' => (string) $single['labelData'],
            ];

            foreach ($docs as $doc) {
                $docType = (string) ($doc['document_type'] ?? '');
                $docMime = strtolower((string) ($doc['mime_type'] ?? ''));
                $docExt = str_contains($docMime, 'pdf') ? 'pdf' : 'bin';
                if ($docType === CustomsDocumentService::TYPE_CN23) {
                    $files[] = [
                        'filename' => sprintf('dhl_cn23_%s.%s', $shipmentId, $docExt),
                        'content' => (string) $doc['content'],
                    ];
                } elseif ($docType === CustomsDocumentService::TYPE_PROFORMA) {
                    $files[] = [
                        'filename' => sprintf('dhl_proforma_%s.%s', $shipmentId, $docExt),
                        'content' => (string) $doc['content'],
                    ];
                }
            }

            return $this->createZipArchive($files, sprintf('dhl_label_%s.zip', $shipmentId));
        }

        return $this->combineLabelsWithCustoms($items, $entityIdByShipmentId, $customsDocsByEntityId);
    }

    /**
     * Get return label PDF/binary content for given shipment entity IDs.
     *
     * @param int[] $entityIds Array of Magento DHL shipment entity IDs
     * @param string|null $overrideLabelType Optional explicit label type override
     *
     * @return array
     * @throws LocalizedException
     */
    public function getReturnLabelContent(array $entityIds, ?string $overrideLabelType = null): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (empty($entityIds)) {
            throw new LocalizedException(__('No shipments selected for return label printing.'));
        }

        $defaultConfigLabelType = (string) $this->scopeConfig->getValue(self::XML_PATH_DEFAULT_LABEL_TYPE) ?: 'BLP';
        $itemsToPrint = [];

        foreach ($entityIds as $id) {
            try {
                $shipment = $this->shipmentDataRepository->getById($id);
                $returnShipmentId = $shipment->getReturnShipmentId();
                if (empty($returnShipmentId)) {
                    continue;
                }

                $labelType = $overrideLabelType;
                if (empty($labelType)) {
                    $shipmentSettings = $this->getShipmentSettings($shipment);
                    $labelType = !empty($shipmentSettings['returnLabelType'])
                        ? (string) $shipmentSettings['returnLabelType']
                        : (!empty($shipmentSettings['labelType'])
                            ? (string) $shipmentSettings['labelType']
                            : null);
                }

                $returnIds = array_filter(array_map('trim', explode(',', (string) $returnShipmentId)));
                foreach ($returnIds as $singleReturnId) {
                    if (!empty($singleReturnId)) {
                        $itemsToPrint[] = [
                            'shipmentId' => $singleReturnId,
                            'labelType' => $labelType ?: $defaultConfigLabelType,
                        ];
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning(sprintf(
                    '[LabelPrintingService] Could not load shipment entity #%d for return label: %s',
                    $id,
                    $e->getMessage()
                ));
            }
        }

        if (empty($itemsToPrint)) {
            throw new LocalizedException(
                __('None of the selected shipments have valid DHL return shipment IDs.')
            );
        }

        $credentials = $this->credentialsProvider->getCredentials();

        $result = $this->client->getLabels($credentials, $itemsToPrint);

        if (!$result->isSuccess()) {
            throw new LocalizedException(
                __('Failed to retrieve return labels from DHL: %1', $result->getErrorMessage() ?? __('Unknown API error'))
            );
        }

        $items = $result->getItems();
        if (empty($items)) {
            throw new LocalizedException(__('No return label data returned by DHL.'));
        }

        if (count($items) === 1) {
            $single = $items[0];
            $ext = str_contains($single['mimeType'], 'pdf') ? 'pdf' : 'txt';
            return [
                'content' => $single['labelData'],
                'mimeType' => $single['mimeType'],
                'filename' => sprintf('dhl_return_label_%s.%s', $single['shipmentId'], $ext),
            ];
        }

        return $this->combineLabels($items);
    }

    /**
     * Get return QR code PDF/binary content for given shipment entity IDs.
     *
     * @param int[] $entityIds Array of Magento DHL shipment entity IDs
     * @param string|null $overrideLabelType Optional explicit QR code type override
     *
     * @return array
     * @throws LocalizedException
     */
    public function getReturnQrCodeContent(array $entityIds, ?string $overrideLabelType = null): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (empty($entityIds)) {
            throw new LocalizedException(__('No shipments selected for return QR code printing.'));
        }

        $itemsToPrint = [];

        foreach ($entityIds as $id) {
            try {
                $shipment = $this->shipmentDataRepository->getById($id);
                $returnShipmentId = $shipment->getReturnShipmentId();
                if (empty($returnShipmentId)) {
                    continue;
                }

                $shipmentSettings = $this->getShipmentSettings($shipment);
                $qrLabelType = $overrideLabelType
                    ?: (!empty($shipmentSettings['returnQrCodeType'])
                        ? (string) $shipmentSettings['returnQrCodeType']
                        : 'QR_PDF');

                $returnIds = array_filter(array_map('trim', explode(',', (string) $returnShipmentId)));
                foreach ($returnIds as $singleReturnId) {
                    if (!empty($singleReturnId)) {
                        $itemsToPrint[] = [
                            'shipmentId' => $singleReturnId,
                            'labelType' => $qrLabelType,
                        ];
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning(sprintf(
                    '[LabelPrintingService] Could not load shipment entity #%d for return QR code: %s',
                    $id,
                    $e->getMessage()
                ));
            }
        }

        if (empty($itemsToPrint)) {
            throw new LocalizedException(
                __('None of the selected shipments have valid DHL return shipment IDs.')
            );
        }

        $credentials = $this->credentialsProvider->getCredentials();

        $result = $this->client->getLabels($credentials, $itemsToPrint);

        if (!$result->isSuccess()) {
            throw new LocalizedException(
                __('Failed to retrieve return QR code from DHL: %1', $result->getErrorMessage() ?? __('Unknown API error'))
            );
        }

        $items = $result->getItems();
        if (empty($items)) {
            throw new LocalizedException(__('No return QR code data returned by DHL.'));
        }

        if (count($items) === 1) {
            $single = $items[0];
            $ext = str_contains($single['mimeType'], 'png') ? 'png' : (str_contains($single['mimeType'], 'pdf') ? 'pdf' : 'pdf');
            return [
                'content' => $single['labelData'],
                'mimeType' => $single['mimeType'],
                'filename' => sprintf('dhl_return_qr_%s.%s', $single['shipmentId'], $ext),
            ];
        }

        return $this->combineLabels($items);
    }

    /**
     * Combine multiple shipment labels and their customs documents into a single PDF or ZIP archive.
     *
     * @param array $items
     * @param array $entityIdByShipmentId
     * @param array $customsDocsByEntityId
     *
     * @return array
     * @throws Zend_Pdf_Exception
     */
    private function combineLabelsWithCustoms(
        array $items,
        array $entityIdByShipmentId,
        array $customsDocsByEntityId
    ): array {
        $hasNonPdf = false;
        foreach ($items as $item) {
            if (!str_contains(strtolower((string) ($item['mimeType'] ?? '')), 'pdf')) {
                $hasNonPdf = true;
                break;
            }
        }

        if (!$hasNonPdf) {
            foreach ($items as $item) {
                $shipmentId = (string) $item['shipmentId'];
                $entityId = $entityIdByShipmentId[$shipmentId] ?? null;
                $docs = $entityId ? ($customsDocsByEntityId[$entityId] ?? []) : [];
                foreach ($docs as $doc) {
                    if (!str_contains(strtolower((string) ($doc['mime_type'] ?? '')), 'pdf')) {
                        $hasNonPdf = true;
                        break 2;
                    }
                }
            }
        }

        if ($hasNonPdf) {
            $files = [];
            foreach ($items as $index => $item) {
                $shipmentId = (string) ($item['shipmentId'] ?? ($index + 1));
                $mime = strtolower((string) ($item['mimeType'] ?? ''));
                $ext = str_contains($mime, 'pdf') ? 'pdf' : (str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'zpl') ? 'zpl' : 'bin'));
                $files[] = [
                    'filename' => sprintf('dhl_label_%s.%s', $shipmentId, $ext),
                    'content' => (string) $item['labelData'],
                ];

                $entityId = $entityIdByShipmentId[$shipmentId] ?? null;
                $docs = $entityId ? ($customsDocsByEntityId[$entityId] ?? []) : [];
                foreach ($docs as $doc) {
                    $docType = (string) ($doc['document_type'] ?? '');
                    $docMime = strtolower((string) ($doc['mime_type'] ?? ''));
                    $docExt = str_contains($docMime, 'pdf') ? 'pdf' : 'bin';
                    if ($docType === CustomsDocumentService::TYPE_CN23) {
                        $files[] = [
                            'filename' => sprintf('dhl_cn23_%s.%s', $shipmentId, $docExt),
                            'content' => (string) $doc['content'],
                        ];
                    } elseif ($docType === CustomsDocumentService::TYPE_PROFORMA) {
                        $files[] = [
                            'filename' => sprintf('dhl_proforma_%s.%s', $shipmentId, $docExt),
                            'content' => (string) $doc['content'],
                        ];
                    }
                }
            }

            return $this->createZipArchive($files, sprintf('dhl_labels_%s.zip', date('Ymd_His')));
        }

        $allPdfStrings = [];
        foreach ($items as $item) {
            $allPdfStrings[] = $item['labelData'];
            $shipmentId = (string) $item['shipmentId'];
            $entityId = $entityIdByShipmentId[$shipmentId] ?? null;
            $docs = $entityId ? ($customsDocsByEntityId[$entityId] ?? []) : [];
            foreach ($docs as $doc) {
                $allPdfStrings[] = $doc['content'];
            }
        }

        $combinedContent = $this->mergePdfDocuments($allPdfStrings);

        return [
            'content' => $combinedContent,
            'mimeType' => 'application/pdf',
            'filename' => sprintf('dhl_labels_%s.pdf', date('Ymd_His')),
        ];
    }

    /**
     * Combine multiple label binary data items into a single document.
     *
     * @param array $items Array of label items [['shipmentId' => string, 'labelData' => string, 'mimeType' => string]]
     *
     * @return array
     * @throws Zend_Pdf_Exception
     */
    private function combineLabels(array $items): array
    {
        $hasNonPdf = false;
        foreach ($items as $item) {
            if (!str_contains(strtolower((string) ($item['mimeType'] ?? '')), 'pdf')) {
                $hasNonPdf = true;
                break;
            }
        }

        if ($hasNonPdf) {
            $files = [];
            foreach ($items as $index => $item) {
                $mime = strtolower((string) ($item['mimeType'] ?? ''));
                $ext = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'zpl') ? 'zpl' : 'bin');
                $files[] = [
                    'filename' => sprintf('dhl_label_%s.%s', $item['shipmentId'] ?? ($index + 1), $ext),
                    'content' => (string) $item['labelData'],
                ];
            }
            return $this->createZipArchive($files, sprintf('dhl_labels_%s.zip', date('Ymd_His')));
        }

        $pdfStrings = array_column($items, 'labelData');
        $combinedContent = $this->mergePdfDocuments($pdfStrings);

        return [
            'content' => $combinedContent,
            'mimeType' => 'application/pdf',
            'filename' => sprintf('dhl_labels_%s.pdf', date('Ymd_His')),
        ];
    }

    /**
     * Create a ZIP archive from an array of file entries.
     *
     * @param array $files Array of ['filename' => string, 'content' => string]
     * @param string $zipFilename
     *
     * @return array
     */
    private function createZipArchive(array $files, string $zipFilename): array
    {
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'dhl_zip_'); // phpcs:ignore
        if ($tempFile !== false && $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                $zip->addFromString($file['filename'], (string) $file['content']);
            }
            $zip->close();

            $zipContent = null;
            try {
                $zipContent = $this->fileDriver->fileGetContents($tempFile);
            } catch (FileSystemException $e) {
                $this->logger->warning(sprintf('Failed to read temporary ZIP label file: %s', $e->getMessage()));
            }

            try {
                if ($this->fileDriver->isExists($tempFile)) {
                    $this->fileDriver->deleteFile($tempFile);
                }
            } catch (FileSystemException $e) {
                $this->logger->warning(sprintf('Failed to clean up temporary ZIP label file: %s', $e->getMessage()));
            }

            if ($zipContent !== null) {
                return [
                    'content' => $zipContent,
                    'mimeType' => 'application/zip',
                    'filename' => $zipFilename,
                ];
            }
        }

        $combinedText = '';
        foreach ($files as $file) {
            $combinedText .= $file['content'] . "\n\n";
        }
        return [
            'content' => trim($combinedText),
            'mimeType' => 'text/plain',
            'filename' => str_replace('.zip', '.txt', $zipFilename),
        ];
    }

    /**
     * Merge multiple PDF binary strings with Magento's label generator.
     *
     * @param array $pdfStrings
     *
     * @return string
     * @throws Zend_Pdf_Exception
     */
    private function mergePdfDocuments(array $pdfStrings): string
    {
        $combinedPdf = $this->labelGenerator->combineLabelsPdf($pdfStrings);

        return $combinedPdf->render();
    }

    /**
     * Read the saved shipment settings from the serialized shipment payload.
     *
     * @param ShipmentDataInterface $shipment
     *
     * @return array
     */
    private function getShipmentSettings(ShipmentDataInterface $shipment): array
    {
        $rawPayload = $shipment->getShipmentData();
        if ($rawPayload === null || $rawPayload === '') {
            return [];
        }

        try {
            $payload = $this->serializer->unserialize($rawPayload);
        } catch (Throwable) {
            return [];
        }

        return is_array($payload) && is_array($payload['shipmentSettings'] ?? null)
            ? $payload['shipmentSettings']
            : [];
    }
}
