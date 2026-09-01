<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\System\Config;

use Exception;
use Ideo\Dhl\Model\MethodCatalog;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;

class ExportTablerates extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Shipping::carriers';

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param ResourceConnection $resourceConnection
     * @param MethodCatalog $methodCatalog
     */
    public function __construct(
        Context $context,
        private readonly FileFactory $fileFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly MethodCatalog $methodCatalog
    ) {
        parent::__construct($context);
    }

    /**
     * Export DHL table rates matrix to CSV file download response.
     *
     * @return ResponseInterface
     * @throws Exception
     */
    public function execute(): ResponseInterface
    {
        $fileName = 'dhl_tablerates.csv';
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('ideo_dhl_tablerate');

        $select = $connection->select()
            ->from($tableName, [
                'website_id',
                'method_code',
                'condition_type',
                'condition_from',
                'condition_to',
                'price',
            ]);

        $rows = $connection->fetchAll($select);

        $methodOrder = array_flip(array_column($this->methodCatalog->getMethods(), 'code'));

        usort($rows, static function (array $a, array $b) use ($methodOrder): int {
            if ((int)$a['website_id'] !== (int)$b['website_id']) {
                return (int)$a['website_id'] <=> (int)$b['website_id'];
            }
            $orderA = $methodOrder[$a['method_code']] ?? 999;
            $orderB = $methodOrder[$b['method_code']] ?? 999;
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }
            if ($a['condition_type'] !== $b['condition_type']) {
                return strcmp((string)$a['condition_type'], (string)$b['condition_type']);
            }
            return (float)$a['condition_from'] <=> (float)$b['condition_from'];
        });

        $content = "Website,Method Code,Condition Type,Value From,Value To,Price\n";
        foreach ($rows as $row) {
            $content .= sprintf(
                "%d,%s,%s,%.4f,%.4f,%.4f\n",
                (int)$row['website_id'],
                $row['method_code'],
                $row['condition_type'],
                (float)$row['condition_from'],
                (float)$row['condition_to'],
                (float)$row['price']
            );
        }

        return $this->fileFactory->create(
            $fileName,
            [
                'type' => 'string',
                'value' => $content,
                'rm' => true,
            ],
            'tmp',
            'text/csv'
        );
    }
}
