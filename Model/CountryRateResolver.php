<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Magento\Framework\App\ResourceConnection;

class CountryRateResolver
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Resolve country shipping price from international country rate matrix.
     *
     * @param int $websiteId
     * @param string $methodCode
     * @param string $countryId
     *
     * @return float|null
     */
    public function resolveRate(int $websiteId, string $methodCode, string $countryId): ?float
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlpoland_country_rate');

        $select = $connection->select()
            ->from($tableName, ['price'])
            ->where('website_id IN (?)', [0, $websiteId])
            ->where('method_code = ?', $methodCode)
            ->where('country_id = ?', strtoupper(trim($countryId)))
            ->order('website_id DESC')
            ->limit(1);

        $result = $connection->fetchOne($select);

        if ($result === false || $result === null) {
            return null;
        }

        return (float)$result;
    }

    /**
     * Retrieve international country rates for export by website ID.
     *
     * @param int $websiteId
     * @param bool $fallbackToDefault
     *
     * @return array
     */
    public function getRatesForExport(int $websiteId, bool $fallbackToDefault = true): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlpoland_country_rate');

        $select = $connection->select()
            ->from($tableName, [
                'method_code',
                'country_id',
                'price',
            ])
            ->where('website_id = ?', $websiteId)
            ->order(['method_code ASC', 'country_id ASC']);

        $rows = $connection->fetchAll($select);
        if (empty($rows) && $websiteId !== 0 && $fallbackToDefault) {
            $defaultSelect = $connection->select()
                ->from($tableName, [
                    'method_code',
                    'country_id',
                    'price',
                ])
                ->where('website_id = 0')
                ->order(['method_code ASC', 'country_id ASC']);
            $rows = $connection->fetchAll($defaultSelect);
        }

        return $rows;
    }
}
