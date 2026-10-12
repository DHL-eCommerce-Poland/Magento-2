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
     */
    public function __construct(
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly LabelGenerator $labelGenerator,
        private readonly FileDriver $fileDriver
    ) {
    }

    /**
     * Get label PDF/binary content for given shipment entity IDs.
     *
     * @param int[] $entityIds Array of Magento DHL shipment entity IDs
     * @param string|null $overrideLabelType Optional explicit label type override
     *
     * @return array
     * @throws LocalizedException
     */
    public function getLabelsContent(array $entityIds, ?string $overrideLabelType = null): array
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if (empty($entityIds)) {
            throw new LocalizedException(__('No shipments selected for label printing.'));
        }

        $defaultConfigLabelType = (string) $this->scopeConfig->getValue(self::XML_PATH_DEFAULT_LABEL_TYPE) ?: 'BLP';
        $itemsToPrint = [];

        foreach ($entityIds as $id) {
            try {
                $shipment = $this->shipmentDataRepository->getById($id);
                $dhlShipmentId = $shipment->getShipmentId();
                if (empty($dhlShipmentId)) {
                    continue;
                }

                $labelType = $overrideLabelType;
                if (empty($labelType)) {
                    $shipmentSettings = $this->getShipmentSettings($shipment);
                    $labelType = !empty($shipmentSettings['labelType'])
                        ? (string) $shipmentSettings['labelType']
                        : null;
                }

                $itemsToPrint[] = [
                    'shipmentId' => (string) $dhlShipmentId,
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

        if (count($items) === 1) {
            $single = $items[0];
            $ext = str_contains($single['mimeType'], 'pdf') ? 'pdf' : 'txt';
            return [
                'content' => $single['labelData'],
                'mimeType' => $single['mimeType'],
                'filename' => sprintf('dhl_label_%s.%s', $single['shipmentId'], $ext),
            ];
        }

        return $this->combineLabels($items);
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
     * Combine multiple label binary data items into a single document.
     *
     * @param array $items Array of label items [['shipmentId' => string, 'labelData' => string, 'mimeType' => string]]
     *
     * @return array{content: string, mimeType: string, filename: string}
     */
    private function combineLabels(array $items): array
    {
        $hasNonPdf = false;
        foreach ($items as $item) {
            if (!str_contains($item['mimeType'], 'pdf')) {
                $hasNonPdf = true;
                break;
            }
        }

        if ($hasNonPdf) {
            $zip = new ZipArchive();
            $tempFile = tempnam(sys_get_temp_dir(), 'dhl_zip_'); // phpcs:ignore
            if ($tempFile !== false && $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($items as $index => $item) {
                    $mime = strtolower((string) ($item['mimeType'] ?? ''));
                    $ext = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'zpl') ? 'zpl' : 'bin');
                    $filename = sprintf('dhl_label_%s.%s', $item['shipmentId'] ?? ($index + 1), $ext);
                    $zip->addFromString($filename, (string) $item['labelData']);
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
                        'filename' => sprintf('dhl_labels_%s.zip', date('Ymd_His')),
                    ];
                }
            }

            $combinedText = '';
            foreach ($items as $item) {
                $combinedText .= $item['labelData'] . "\n\n";
            }
            return [
                'content' => trim($combinedText),
                'mimeType' => 'text/plain',
                'filename' => sprintf('dhl_labels_%s.txt', date('Ymd_His')),
            ];
        }

        $combinedContent = $this->mergePdfDocuments($items);

        return [
            'content' => $combinedContent,
            'mimeType' => 'application/pdf',
            'filename' => sprintf('dhl_labels_%s.pdf', date('Ymd_His')),
        ];
    }

    /**
     * Merge multiple PDF binary strings with Magento's label generator.
     *
     * @param array $items
     *
     * @return string
     * @throws Zend_Pdf_Exception
     */
    private function mergePdfDocuments(array $items): string
    {
        $pdfStrings = array_column($items, 'labelData');
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
