<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Backend;

use Ideo\Dhl\Model\MethodCatalog;
use Magento\Config\Model\Config\Backend\File;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class CountryRate extends File
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
     * @param MethodCatalog $methodCatalog
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param WriterInterface $configWriter
     * @param FileIo $fileIo
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
        private readonly MethodCatalog $methodCatalog,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly WriterInterface $configWriter,
        private readonly FileIo $fileIo,
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
     * Parse CSV content into verified country rates rows array.
     *
     * @param string $content
     *
     * @return array
     * @throws LocalizedException
     */
    public function parseCsvContent(string $content): array
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

        $hasCountry = in_array('country', $normalizedHeader, true) || in_array('country code', $normalizedHeader, true);
        $hasPrice = in_array('price', $normalizedHeader, true);
        if (!$hasCountry || !$hasPrice) {
            throw new LocalizedException(
                __('Invalid CSV header structure. Expected columns: Method Code, Country, Price.')
            );
        }

        $colMap = array_flip($normalizedHeader);
        $countryKey = isset($colMap['country']) ? 'country' : 'country code';
        $methodKey = isset($colMap['method code']) ? 'method code' : (isset($colMap['method']) ? 'method' : null);

        $validMethodCodes = array_column($this->methodCatalog->getMethods(), 'code');
        $validCountryCodes = array_flip($this->countryCollectionFactory->create()->getAllIds());

        $rows = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $data = str_getcsv($line, $delimiter, '"', '\\');
            $lineNum = $index + 2;

            $country = strtoupper(trim((string)($data[$colMap[$countryKey]] ?? '')));
            $priceStr = trim((string)($data[$colMap['price']] ?? ''));
            $methodCode = $methodKey !== null && isset($data[$colMap[$methodKey]])
                ? trim((string)$data[$colMap[$methodKey]])
                : 'connect';

            if (!isset($validCountryCodes[$country])) {
                throw new LocalizedException(
                    __(
                        'Invalid country code "%1" on line %2. Please use 2-letter ISO country codes.',
                        $country,
                        $lineNum
                    )
                );
            }

            if (!in_array($methodCode, $validMethodCodes, true)) {
                throw new LocalizedException(
                    __('Unsupported method code "%1" on line %2.', $methodCode, $lineNum)
                );
            }

            if (!is_numeric($priceStr) || (float)$priceStr < 0) {
                throw new LocalizedException(
                    __('Invalid price value "%1" on line %2.', $priceStr, $lineNum)
                );
            }

            $rows[] = [
                'method_code' => $methodCode,
                'country_id' => $country,
                'price' => (float)$priceStr,
            ];
        }

        return $rows;
    }

    /**
     * @var array
     */
    private array $importedRows = [];

    /**
     * Upload CSV, parse, save to database and sync allowed countries.
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
            $pathInfo = $this->fileIo->getPathInfo($savedFileName);
            $baseFileName = $pathInfo['basename'] ?? $savedFileName;
            $relativeFilePath = 'import/dhl/' . $baseFileName;

            if ($this->_mediaDirectory->isExist($relativeFilePath)) {
                $content = $this->_mediaDirectory->readFile($relativeFilePath);
                $rows = $this->parseCsvContent($content);

                $websiteId = $this->resolveCurrentWebsiteId();
                $this->saveCountryRates($websiteId, $rows);
                $this->importedRows = $rows;
                $this->syncConfigSpecificCountries($rows);

                $dateStr = date('Y-m-d_H-i-s');
                $timestampedFileName = sprintf('dhl_international_rates_%s.csv', $dateStr);
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
     * Clean config cache and ensure country sync after full transaction commit.
     *
     * @return $this
     */
    public function afterSave()
    {
        parent::afterSave();

        if (!empty($this->importedRows)) {
            $this->syncConfigSpecificCountries($this->importedRows);
            $this->importedRows = [];
        }

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
     * Synchronize specificcountry configuration for all methods present in the imported file.
     *
     * @param array $rows
     *
     * @return void
     */
    public function syncConfigSpecificCountries(array $rows): void
    {
        $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = (int)($this->getScopeId() ?: 0);

        $methodCountries = [];
        foreach ($rows as $row) {
            $methodCountries[$row['method_code']][$row['country_id']] = true;
        }

        foreach ($methodCountries as $methodCode => $countries) {
            $countryList = implode(',', array_keys($countries));
            $this->configWriter->save(
                'carriers/dhlparcel/method_' . $methodCode . '_sallowspecific',
                '1',
                $scope,
                $scopeId
            );
            $this->configWriter->save(
                'carriers/dhlparcel/method_' . $methodCode . '_specificcountry',
                $countryList,
                $scope,
                $scopeId
            );
        }
    }

    /**
     * Remove default-scope country restrictions for the supplied shipping methods.
     *
     * @param string[] $methodCodes
     *
     * @return void
     */
    public function clearConfigSpecificCountries(array $methodCodes): void
    {
        $methodCodes = array_values(array_unique(array_filter(
            array_map(static fn ($methodCode) => is_string($methodCode) ? trim($methodCode) : '', $methodCodes),
            static fn (string $methodCode): bool => $methodCode !== ''
        )));

        if ($methodCodes === []) {
            return;
        }

        foreach ($methodCodes as $methodCode) {
            $this->configWriter->delete(
                'carriers/dhlparcel/method_' . $methodCode . '_sallowspecific',
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
            $this->configWriter->delete(
                'carriers/dhlparcel/method_' . $methodCode . '_specificcountry',
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        }

        $this->cacheTypeList->cleanType('config');
    }

    /**
     * Save country rates into database table for the given website and methods.
     *
     * @param int $websiteId
     * @param array $rows
     *
     * @return void
     * @throws LocalizedException
     */
    public function saveCountryRates(int $websiteId, array $rows): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlpoland_country_rate');

        $affectedMethods = array_values(array_unique(array_column($rows, 'method_code')));

        $insertData = [];
        foreach ($rows as $row) {
            $insertData[] = [
                'website_id' => $websiteId,
                'method_code' => $row['method_code'],
                'country_id' => $row['country_id'],
                'price' => $row['price'],
            ];
        }

        $connection->beginTransaction();
        try {
            if (!empty($affectedMethods)) {
                $connection->delete($tableName, [
                    'website_id = ?' => $websiteId,
                    'method_code IN (?)' => $affectedMethods,
                ]);
            }
            if (!empty($insertData)) {
                $connection->insertMultiple($tableName, $insertData);
            }
            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();
            throw new LocalizedException(__('Failed to save country rates: %1', $e->getMessage()), $e);
        }
    }

    /**
     * Return allowed extensions for country rates CSV import.
     *
     * @return string[]
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _getAllowedExtensions(): array
    {
        return ['csv'];
    }

    /**
     * Return upload directory path for country rates CSV files.
     *
     * @return string
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _getUploadDir(): string
    {
        return $this->_mediaDirectory->getAbsolutePath('import/dhl');
    }
}
