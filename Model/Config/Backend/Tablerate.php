<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Tablerate extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
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
        private readonly ResourceConnection $resourceConnection,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
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
     * Import uploaded CSV file data into database.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function afterSave(): self
    {
        $value = $this->getValue();
        if (is_array($value) && !empty($value['tmp_name'])) {
            $tmpFile = $value['tmp_name'];
            if (file_exists($tmpFile)) {
                $content = file_get_contents($tmpFile);
                if ($content !== false) {
                    $rows = $this->parseCsvContent($content);
                    $this->saveTableRates($rows);
                }
            }
        }

        return parent::afterSave();
    }

    /**
     * Bulk save parsed table rates into database table.
     *
     * @param array $rows
     * @return void
     * @throws LocalizedException
     */
    public function saveTableRates(array $rows): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('ideo_dhl_tablerate');

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
}
