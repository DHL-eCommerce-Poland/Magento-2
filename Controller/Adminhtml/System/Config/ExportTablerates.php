<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\System\Config;

use Exception;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\TableRateResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\StoreManagerInterface;

class ExportTablerates extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Shipping::carriers';

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param MethodCatalog $methodCatalog
     * @param TableRateResolver $tableRateResolver
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly FileFactory $fileFactory,
        private readonly MethodCatalog $methodCatalog,
        private readonly TableRateResolver $tableRateResolver,
        private readonly StoreManagerInterface $storeManager
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

        $websiteParam = $this->getRequest()?->getParam('website');
        $websiteId = 0;
        if ($websiteParam !== null && $websiteParam !== '') {
            try {
                $websiteId = (int)$this->storeManager->getWebsite($websiteParam)->getId();
            } catch (Exception) {
                $websiteId = is_numeric($websiteParam) ? (int)$websiteParam : 0;
            }
        }

        $rows = $this->tableRateResolver->getRatesForExport($websiteId);

        $methodOrder = array_flip(array_column($this->methodCatalog->getMethods(), 'code'));

        usort($rows, static function (array $a, array $b) use ($methodOrder): int {
            $orderA = $methodOrder[$a['method_code']] ?? PHP_INT_MAX;
            $orderB = $methodOrder[$b['method_code']] ?? PHP_INT_MAX;
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }
            $countryA = (string)($a['country_id'] ?? '');
            $countryB = (string)($b['country_id'] ?? '');
            if ($countryA !== $countryB) {
                return strcmp($countryA, $countryB);
            }
            if ($a['condition_type'] !== $b['condition_type']) {
                return strcmp((string)$a['condition_type'], (string)$b['condition_type']);
            }
            return (float)$a['condition_from'] <=> (float)$b['condition_from'];
        });

        $content = "Method Code,Country,Condition Type,Value From,Value To,Price\n";
        foreach ($rows as $row) {
            $country = !empty($row['country_id']) ? strtoupper((string)$row['country_id']) : 'PL';
            $content .= sprintf(
                "%s,%s,%s,%.4f,%.4f,%.4f\n",
                $row['method_code'],
                $country,
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
