<?php

declare(strict_types=1);

namespace Ideo\Dhl\Setup\Patch\Data;

use Ideo\Dhl\Model\Config\Backend\CountryRate;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class PopulateDefaultCountryRates implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @param ModuleDirReader $moduleDirReader
     * @param Csv $csvReader
     * @param ResourceConnection $resourceConnection
     * @param CountryRate $countryRateBackend
     */
    public function __construct(
        private readonly ModuleDirReader $moduleDirReader,
        private readonly Csv $csvReader,
        private readonly ResourceConnection $resourceConnection,
        private readonly CountryRate $countryRateBackend
    ) {
    }

    /**
     * Apply data patch to populate default country rates.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function apply(): self
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlpoland_country_rate');

        $count = (int)$connection->fetchOne($connection->select()->from($tableName, ['COUNT(*)']));
        if ($count > 0) {
            return $this;
        }

        $etcDir = $this->moduleDirReader->getModuleDir('etc', 'Ideo_Dhl');
        $filePath = $etcDir . '/dhl_international_rates_default.csv';

        $data = $this->csvReader->getData($filePath);
        if (empty($data)) {
            return $this;
        }

        $csvText = '';
        foreach ($data as $row) {
            $escaped = array_map(
                static fn ($cell) => '"' . str_replace('"', '""', (string)$cell) . '"',
                $row
            );
            $csvText .= implode(',', $escaped) . "\n";
        }

        $parsedRows = $this->countryRateBackend->parseCsvContent($csvText);
        if (!empty($parsedRows)) {
            $this->countryRateBackend->saveCountryRates(0, $parsedRows);
            $this->countryRateBackend->syncConfigSpecificCountries($parsedRows);
        }

        return $this;
    }

    /**
     * Revert data patch changes.
     *
     * @return void
     */
    public function revert(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlpoland_country_rate');
        $connection->delete($tableName);
    }

    /**
     * Get array of patch dependencies.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases for patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
