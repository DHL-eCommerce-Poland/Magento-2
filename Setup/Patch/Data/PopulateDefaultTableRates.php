<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Setup\Patch\Data;

use DhlPoland\Dhl\Model\Config\Backend\Tablerate;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class PopulateDefaultTableRates implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @param ModuleDirReader $moduleDirReader
     * @param Csv $csvReader
     * @param ResourceConnection $resourceConnection
     * @param Tablerate $tablerateBackend
     */
    public function __construct(
        private readonly ModuleDirReader $moduleDirReader,
        private readonly Csv $csvReader,
        private readonly ResourceConnection $resourceConnection,
        private readonly Tablerate $tablerateBackend
    ) {
    }

    /**
     * Apply data patch to populate default table rates.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function apply(): self
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('dhlpoland_tablerate');

        $count = (int)$connection->fetchOne($connection->select()->from($tableName, ['COUNT(*)']));
        if ($count > 0) {
            return $this;
        }

        $etcDir = $this->moduleDirReader->getModuleDir('etc', 'DhlPoland_Dhl');
        $filePath = $etcDir . '/dhl_tablerates_default.csv';

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

        $parsedRows = $this->tablerateBackend->parseCsvContent($csvText);
        if (!empty($parsedRows)) {
            $this->tablerateBackend->saveTableRates($parsedRows);
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
        $tableName = $this->resourceConnection->getTableName('dhlpoland_tablerate');
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
