<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Magento\Framework\App\ResourceConnection;

class TableRateResolver
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Resolve shipping price from table rates matrix for given criteria.
     *
     * @param int $websiteId
     * @param string $methodCode
     * @param string $conditionType
     * @param float $value
     *
     * @return float|null
     */
    public function resolveRate(
        int $websiteId,
        string $methodCode,
        string $conditionType,
        float $value
    ): ?float {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlparcel_tablerate');

        $select = $connection->select()
            ->from($tableName, ['price'])
            ->where('website_id IN (?)', [0, $websiteId])
            ->where('method_code = ?', $methodCode)
            ->where('condition_type = ?', $conditionType)
            ->where('condition_from <= ?', $value)
            ->where('condition_to >= ?', $value)
            ->order('website_id DESC')
            ->limit(1);

        $result = $connection->fetchOne($select);

        if ($result === false || $result === null) {
            return null;
        }

        return (float)$result;
    }
}
