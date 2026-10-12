<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Psr\Log\LoggerInterface;
use Throwable;
use Zend_Pdf;
use ZipArchive;

class DocumentDownloadService
{
    /**
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param Filesystem $filesystem
     * @param LabelGenerator $labelGenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly Filesystem $filesystem,
        private readonly LabelGenerator $labelGenerator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get consignment note scan document content.
     *
     * @param int $entityId Magento shipment data entity ID
     *
     * @return array
     * @throws LocalizedException
     */
    public function getScanContent(int $entityId): array
    {
        $shipment = $this->getShipmentEntity($entityId);
        $dhlShipmentId = $shipment->getShipmentId();

        if (empty($dhlShipmentId)) {
            throw new LocalizedException(__('Shipment entity #%1 does not have a DHL shipment ID.', $entityId));
        }

        $credentials = $this->credentialsProvider->getCredentials();
        $result = $this->client->getShipmentScan($credentials, (string) $dhlShipmentId);

        if (!$result->isSuccess()) {
            $errorMsg = $result->getErrorMessage() ?? __('Unknown API error');
            throw new LocalizedException(
                __('Failed to retrieve waybill scan from DHL: %1', $errorMsg)
            );
        }

        $content = $result->getFileData();
        if (empty($content)) {
            throw new LocalizedException(__('No scan data returned by DHL.'));
        }

        $declaredMime = $result->getMimeType() ?: 'application/pdf';
        $fileInfo = $this->detectFileType($content, $declaredMime);

        return [
            'content' => $content,
            'mimeType' => $fileInfo['mimeType'],
            'filename' => sprintf('dhl_scan_%s.%s', $dhlShipmentId, $fileInfo['extension']),
        ];
    }

    /**
     * Get ePOD document content.
     *
     * @param int $entityId Magento shipment data entity ID
     *
     * @return array
     * @throws LocalizedException
     */
    public function getEpodContent(int $entityId): array
    {
        $shipment = $this->getShipmentEntity($entityId);
        $dhlShipmentId = $shipment->getShipmentId();

        if (empty($dhlShipmentId)) {
            throw new LocalizedException(__('Shipment entity #%1 does not have a DHL shipment ID.', $entityId));
        }

        $credentials = $this->credentialsProvider->getCredentials();
        $result = $this->client->getEpod($credentials, (string) $dhlShipmentId);

        if (!$result->isSuccess()) {
            throw new LocalizedException(
                __('Failed to retrieve ePOD from DHL: %1', $result->getErrorMessage() ?? __('Unknown API error'))
            );
        }

        $content = $result->getFileData();
        if (empty($content)) {
            throw new LocalizedException(__('No ePOD data returned by DHL.'));
        }

        $declaredMime = $result->getMimeType() ?: 'application/pdf';
        $fileInfo = $this->detectFileType($content, $declaredMime);

        return [
            'content' => $content,
            'mimeType' => $fileInfo['mimeType'],
            'filename' => sprintf('dhl_epod_%s.%s', $dhlShipmentId, $fileInfo['extension']),
        ];
    }

    /**
     * Get bulk waybill scan document content for multiple shipment entity IDs.
     *
     * @param int[] $entityIds
     *
     * @return array
     * @throws LocalizedException
     */
    public function getBulkScanContent(array $entityIds): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (empty($entityIds)) {
            throw new LocalizedException(__('No shipments selected for downloading scans.'));
        }

        $items = [];
        foreach ($entityIds as $id) {
            try {
                $items[] = $this->getScanContent($id);
            } catch (Throwable $e) {
                $msg = sprintf(
                    '[DocumentDownloadService] Could not retrieve scan for shipment #%d: %s',
                    $id,
                    $e->getMessage()
                );
                $this->logger->warning($msg);
            }
        }

        if (empty($items)) {
            throw new LocalizedException(__('Could not retrieve scan documents for the selected shipments.'));
        }

        if (count($items) === 1) {
            return $items[0];
        }

        return $this->combineDocuments($items, 'dhl_scans');
    }

    /**
     * Get bulk ePOD document content for multiple shipment entity IDs.
     *
     * @param int[] $entityIds
     *
     * @return array
     * @throws LocalizedException
     */
    public function getBulkEpodContent(array $entityIds): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (empty($entityIds)) {
            throw new LocalizedException(__('No shipments selected for downloading ePODs.'));
        }

        $items = [];
        foreach ($entityIds as $id) {
            try {
                $items[] = $this->getEpodContent($id);
            } catch (Throwable $e) {
                $msg = sprintf(
                    '[DocumentDownloadService] Could not retrieve ePOD for shipment #%d: %s',
                    $id,
                    $e->getMessage()
                );
                $this->logger->warning($msg);
            }
        }

        if (empty($items)) {
            throw new LocalizedException(__('Could not retrieve ePOD documents for the selected shipments.'));
        }

        if (count($items) === 1) {
            return $items[0];
        }

        return $this->combineDocuments($items, 'dhl_epods');
    }

    /**
     * Combine multiple document items into a single PDF if all are PDFs, or into a ZIP archive if non-PDFs are present.
     *
     * @param array[] $items
     * @param string $prefix
     *
     * @return array
     * @throws LocalizedException
     */
    private function combineDocuments(array $items, string $prefix): array
    {
        $hasNonPdf = false;
        foreach ($items as $item) {
            if (!str_contains(strtolower($item['mimeType']), 'pdf')) {
                $hasNonPdf = true;
                break;
            }
        }

        if (!$hasNonPdf) {
            $pdfContent = $this->mergePdfDocuments($items);
            if (!empty($pdfContent)) {
                return [
                    'content' => $pdfContent,
                    'mimeType' => 'application/pdf',
                    'filename' => sprintf('%s_%s.pdf', $prefix, date('Ymd_His')),
                ];
            }
        }

        return $this->createZipArchive($items, $prefix);
    }

    /**
     * Merge multiple PDF binary strings into a single multi-page PDF document.
     *
     * @param array $items
     *
     * @return string
     */
    private function mergePdfDocuments(array $items): string
    {
        $pdfStrings = array_column($items, 'content');

        try {
            $combinedPdf = $this->labelGenerator->combineLabelsPdf($pdfStrings);
            return $combinedPdf->render();
        } catch (Throwable $e) {
            $this->logger->warning('[DocumentDownloadService] LabelGenerator PDF merge failed: ' . $e->getMessage());
        }

        if (class_exists('\Zend_Pdf')) {
            try {
                $targetPdf = new Zend_Pdf();
                foreach ($items as $item) {
                    if (stripos($item['content'], '%PDF-') !== false) {
                        $pdf = Zend_Pdf::parse($item['content']);
                        foreach ($pdf->pages as $page) {
                            $targetPdf->pages[] = clone $page;
                        }
                    }
                }
                return $targetPdf->render();
            } catch (Throwable $e) {
                $this->logger->warning('[DocumentDownloadService] Zend PDF merge failed: ' . $e->getMessage());
            }
        }

        $content = '';
        foreach ($items as $item) {
            $content .= $item['content'];
        }

        return $content;
    }

    /**
     * Package multiple document items into a ZIP archive.
     *
     * @param array $items Array of ['content' => string, 'mimeType' => string, 'filename' => string]
     * @param string $prefix
     *
     * @return array
     * @throws LocalizedException
     */
    private function createZipArchive(array $items, string $prefix): array
    {
        $varTmpDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $relFileName = 'dhl_zip_' . uniqid('', true) . '.tmp';
        $absFilePath = $varTmpDir->getAbsolutePath($relFileName);

        $zip = new ZipArchive();
        if ($zip->open($absFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new LocalizedException(__('Failed to initialize ZIP archive.'));
        }

        try {
            foreach ($items as $index => $item) {
                $filename = !empty($item['filename']) ? $item['filename'] : sprintf('document_%d.bin', $index + 1);
                $zip->addFromString($filename, $item['content']);
            }
            $zip->close();

            $zipContent = $varTmpDir->readFile($relFileName);
        } finally {
            if ($varTmpDir->isExist($relFileName)) {
                $varTmpDir->delete($relFileName);
            }
        }

        if ($zipContent === '') {
            throw new LocalizedException(__('Failed to read generated ZIP archive content.'));
        }

        return [
            'content' => $zipContent,
            'mimeType' => 'application/zip',
            'filename' => sprintf('%s_%s.zip', $prefix, date('Ymd_His')),
        ];
    }

    /**
     * Detect actual MIME type and file extension from binary magic bytes or declared MIME type.
     *
     * @param string $content
     * @param string $declaredMimeType
     *
     * @return array
     */
    private function detectFileType(string $content, string $declaredMimeType): array
    {
        if (str_starts_with($content, "\x89PNG")) {
            return ['mimeType' => 'image/png', 'extension' => 'png'];
        }

        if (str_starts_with($content, '%PDF-')) {
            return ['mimeType' => 'application/pdf', 'extension' => 'pdf'];
        }

        if (str_starts_with($content, "\xFF\xD8\xFF")) {
            return ['mimeType' => 'image/jpeg', 'extension' => 'jpg'];
        }

        if (str_starts_with($content, 'GIF87a') || str_starts_with($content, 'GIF89a')) {
            return ['mimeType' => 'image/gif', 'extension' => 'gif'];
        }

        $cleanMime = strtolower($declaredMimeType);
        if (str_contains($cleanMime, 'png')) {
            return ['mimeType' => 'image/png', 'extension' => 'png'];
        }
        if (str_contains($cleanMime, 'jpeg') || str_contains($cleanMime, 'jpg')) {
            return ['mimeType' => 'image/jpeg', 'extension' => 'jpg'];
        }
        if (str_contains($cleanMime, 'gif')) {
            return ['mimeType' => 'image/gif', 'extension' => 'gif'];
        }

        return ['mimeType' => 'application/pdf', 'extension' => 'pdf'];
    }

    /**
     * Load shipment entity by ID.
     *
     * @param int $entityId
     *
     * @return ShipmentDataInterface
     * @throws LocalizedException
     */
    private function getShipmentEntity(int $entityId): ShipmentDataInterface
    {
        if ($entityId <= 0) {
            throw new LocalizedException(__('Invalid shipment entity ID.'));
        }

        try {
            return $this->shipmentDataRepository->getById($entityId);
        } catch (Throwable $e) {
            throw new LocalizedException(__('Shipment entity #%1 could not be loaded.', $entityId), $e);
        }
    }
}
