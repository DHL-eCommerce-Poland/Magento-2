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

        $parsedRows = $this->getDefaultCountryRateRows();
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
     * @throws LocalizedException
     */
    public function revert(): void
    {
        $parsedRows = $this->getDefaultCountryRateRows();
        $methodCodes = array_values(array_unique(array_column($parsedRows, 'method_code')));
        $this->countryRateBackend->clearConfigSpecificCountries($methodCodes);

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

    /**
     * Load and parse the bundled default country rates used by both patch operations.
     *
     * @return array
     * @throws LocalizedException
     */
    private function getDefaultCountryRateRows(): array
    {
        $etcDir = $this->moduleDirReader->getModuleDir('etc', 'Ideo_Dhl');
        $filePath = $etcDir . '/dhl_international_rates_default.csv';
        $data = $this->csvReader->getData($filePath);
        if (empty($data)) {
            return [];
        }

        $csvText = '';
        foreach ($data as $row) {
            $escaped = array_map(
                static fn ($cell) => '"' . str_replace('"', '""', (string)$cell) . '"',
                $row
            );
            $csvText .= implode(',', $escaped) . "\n";
        }

        return $this->countryRateBackend->parseCsvContent($csvText);
    }
}
