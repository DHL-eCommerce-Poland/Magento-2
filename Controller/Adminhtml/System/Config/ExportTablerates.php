<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\System\Config;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Filesystem\DirectoryList;

class ExportTablerates extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Shipping::carriers';

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        private readonly FileFactory $fileFactory,
        private readonly ResourceConnection $resourceConnection
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
            ])
            ->order(['website_id ASC', 'method_code ASC', 'condition_type ASC', 'condition_from ASC']);

        $rows = $connection->fetchAll($select);

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
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
