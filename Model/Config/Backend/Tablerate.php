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
     *
     * @return array Array of parsed rate rows.
     * @throws LocalizedException If CSV header or content is invalid.
     */
    public function parseCsvContent(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            throw new LocalizedException(__('CSV file is empty.'));
        }

        $header = str_getcsv(array_shift($lines), ',', '"', '\\');
        $normalizedHeader = array_map(static fn ($col) => strtolower(trim((string)$col)), $header);

        $expectedColumns = ['website', 'method code', 'condition type', 'value from', 'value to', 'price'];
        foreach ($expectedColumns as $col) {
            if (!in_array($col, $normalizedHeader, true)) {
                throw new LocalizedException(__('Invalid CSV header structure.'));
            }
        }

        $colMap = array_flip($normalizedHeader);
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $data = str_getcsv($line, ',', '"', '\\');
            if (count($data) < count($expectedColumns)) {
                throw new LocalizedException(
                    __('Invalid data format on line %1.', $index + 2)
                );
            }

            $websiteId = (int)trim($data[$colMap['website']]);
            $methodCode = trim($data[$colMap['method code']]);
            $conditionType = strtolower(trim($data[$colMap['condition type']]));
            $from = (float)trim($data[$colMap['value from']]);
            $to = (float)trim($data[$colMap['value to']]);
            $price = (float)trim($data[$colMap['price']]);

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
                $rows = $this->parseCsvContent($content);
                $this->saveTableRates($rows);

                $dateStr = date('Y-m-d_H-i-s');
                $filename = $pathInfo['filename'] ?? 'dhl_tablerates';
                $extension = $pathInfo['extension'] ?? 'csv';
                $timestampedFileName = sprintf(
                    '%s_%s.%s',
                    $filename,
                    $dateStr,
                    $extension
                );

                $timestampedRelativePath = 'import/dhl/' . $timestampedFileName;
                if ($this->_mediaDirectory->renameFile($relativeFilePath, $timestampedRelativePath)) {
                    $this->setValue($timestampedFileName);
                }
            }
        }

        return $this;
    }

    /**
     * Bulk save parsed table rates into database table.
     *
     * @param array $rows
     *
     * @return void
     * @throws LocalizedException
     */
    public function saveTableRates(array $rows): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlparcel_tablerate');

        $connection->beginTransaction();
        try {
            $connection->delete($tableName);
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
    protected function _getAllowedExtensions(): array
    {
        return ['csv'];
    }

    /**
     * Return upload directory path for table rates CSV files.
     *
     * @return string
     */
    protected function _getUploadDir(): string
    {
        return $this->_mediaDirectory->getAbsolutePath('import/dhl');
    }
}
