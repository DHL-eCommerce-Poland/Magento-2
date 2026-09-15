<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\File;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\ScopeInterface;

class Tablerate extends File
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param UploaderFactory $uploaderFactory
     * @param RequestDataInterface $requestData
     * @param Filesystem $filesystem
     * @param ResourceConnection $resourceConnection
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        UploaderFactory $uploaderFactory,
        RequestDataInterface $requestData,
        Filesystem $filesystem,
        private readonly ResourceConnection $resourceConnection,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Parse CSV file content into tabular rate rows array.
     *
     * @param string $content Raw CSV text content.
     * @param int|null $defaultWebsiteId Optional website ID override.
     *
     * @return array Array of parsed rate rows.
     * @throws LocalizedException If CSV header or content is invalid.
     */
    public function parseCsvContent(string $content, ?int $defaultWebsiteId = null): array
    {
        $cleanContent = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));
        $lines = preg_split('/\r\n|\r|\n/', (string)$cleanContent);
        if (empty($lines) || trim((string)$lines[0]) === '') {
            throw new LocalizedException(__('CSV file is empty.'));
        }

        $headerLine = array_shift($lines);
        $delimiter = (substr_count((string)$headerLine, ';') > substr_count((string)$headerLine, ',')) ? ';' : ',';

        $header = str_getcsv((string)$headerLine, $delimiter, '"', '\\');
        $normalizedHeader = array_map(static fn ($col) => strtolower(trim((string)$col)), $header);

        $expectedColumns = ['method code', 'condition type', 'value from', 'value to', 'price'];
        foreach ($expectedColumns as $col) {
            if (!in_array($col, $normalizedHeader, true)) {
                throw new LocalizedException(
                    __(
                        'Invalid CSV header structure. ' .
                        'Expected columns: Method Code, Condition Type, Value From, Value To, Price.'
                    )
                );
            }
        }

        $colMap = array_flip($normalizedHeader);
        $hasWebsite = in_array('website', $normalizedHeader, true);
        $currentScopeWebsiteId = $defaultWebsiteId ?? $this->resolveCurrentWebsiteId();
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim((string)$line) === '') {
                continue;
            }

            $data = str_getcsv((string)$line, $delimiter, '"', '\\');
            if (count($data) < count($expectedColumns)) {
                throw new LocalizedException(
                    __('Invalid data format on line %1.', $index + 2)
                );
            }

            if ($currentScopeWebsiteId > 0) {
                $websiteId = $currentScopeWebsiteId;
            } elseif ($hasWebsite && isset($data[$colMap['website']])) {
                $websiteId = (int)trim((string)$data[$colMap['website']]);
            } else {
                $websiteId = 0;
            }

            $methodCode = trim((string)$data[$colMap['method code']]);
            $conditionType = strtolower(trim((string)$data[$colMap['condition type']]));
            $from = (float)trim((string)$data[$colMap['value from']]);
            $to = (float)trim((string)$data[$colMap['value to']]);
            $price = (float)trim((string)$data[$colMap['price']]);

            if (!in_array($conditionType, ['weight', 'subtotal'], true)) {
                throw new LocalizedException(
                    __('Unsupported condition type "%1" on line %2.', $conditionType, $index + 2)
                );
            }

            $rows[] = [
                'website_id' => $websiteId,
                'method_code' => $methodCode,
                'condition_type' => $conditionType,
                'condition_from' => $from,
                'condition_to' => $to,
                'price' => $price,
            ];
        }

        return $rows;
    }

    /**
     * Upload CSV file, append timestamp to filename, parse data and save into DB.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave(): self
    {
        $fileData = $this->getFileData();
        parent::beforeSave();
        $savedFileName = (string)$this->getValue();

        if (!empty($fileData) && $savedFileName !== '') {
            $fileIo = new \Magento\Framework\Filesystem\Io\File();
            $pathInfo = $fileIo->getPathInfo($savedFileName);
            $baseFileName = $pathInfo['basename'] ?? $savedFileName;
            $relativeFilePath = 'import/dhl/' . $baseFileName;

            if ($this->_mediaDirectory->isExist($relativeFilePath)) {
                $content = $this->_mediaDirectory->readFile($relativeFilePath);
                $websiteId = $this->resolveCurrentWebsiteId();
                $rows = $this->parseCsvContent($content, $websiteId);
                $this->saveTableRates($rows, $websiteId);

                $dateStr = date('Y-m-d_H-i-s');
                $timestampedFileName = sprintf('dhl_tablerates_%s.csv', $dateStr);
                $timestampedRelativePath = 'import/dhl/' . $timestampedFileName;

                if ($this->_mediaDirectory->isExist($timestampedRelativePath)) {
                    $this->_mediaDirectory->deleteFile($timestampedRelativePath);
                }

                if ($this->_mediaDirectory->renameFile($relativeFilePath, $timestampedRelativePath)) {
                    $this->setValue($timestampedFileName);
                }
            }
        }

        return $this;
    }

    /**
     * Clean config cache after save.
     *
     * @return $this
     */
    public function afterSave()
    {
        parent::afterSave();
        $this->cacheTypeList->cleanType('config');
        return $this;
    }

    /**
     * Resolve website ID based on active configuration scope.
     *
     * @return int
     */
    public function resolveCurrentWebsiteId(): int
    {
        $scope = $this->getScope();
        $scopeId = (int)$this->getScopeId();

        if ($scope === ScopeInterface::SCOPE_WEBSITES) {
            return $scopeId;
        }

        return 0;
    }

    /**
     * Bulk save parsed table rates into database table.
     *
     * @param array $rows
     * @param int|null $websiteId
     *
     * @return void
     * @throws LocalizedException
     */
    public function saveTableRates(array $rows, ?int $websiteId = null): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlparcel_tablerate');

        $affectedWebsiteIds = $websiteId !== null
            ? [$websiteId]
            : array_values(array_unique(array_map(static fn ($r) => (int)$r['website_id'], $rows)));

        if (empty($affectedWebsiteIds)) {
            $affectedWebsiteIds = [0];
        }

        $connection->beginTransaction();
        try {
            $connection->delete($tableName, [
                'website_id IN (?)' => $affectedWebsiteIds,
            ]);
            if (!empty($rows)) {
                $connection->insertMultiple($tableName, $rows);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new LocalizedException(__('Failed to save table rates: %1', $e->getMessage()), $e);
        }
    }

    /**
     * Return allowed extensions for table rates CSV import.
     *
     * @return string[]
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _getAllowedExtensions(): array
    {
        return ['csv'];
    }

    /**
     * Return upload directory path for table rates CSV files.
     *
     * @return string
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _getUploadDir(): string
    {
        return $this->_mediaDirectory->getAbsolutePath('import/dhl');
    }
}
