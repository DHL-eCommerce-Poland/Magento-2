<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\System\Config;

use Exception;
use Ideo\Dhl\Model\CountryRateResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\StoreManagerInterface;

class ExportCountryRates extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Shipping::carriers';

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param CountryRateResolver $countryRateResolver
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly FileFactory $fileFactory,
        private readonly CountryRateResolver $countryRateResolver,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    /**
     * Export DHL international country rates to CSV file download response.
     *
     * @return ResponseInterface
     * @throws Exception
     */
    public function execute(): ResponseInterface
    {
        $fileName = 'dhl_international_rates.csv';

        $websiteParam = $this->getRequest()?->getParam('website');
        $websiteId = 0;
        if ($websiteParam !== null && $websiteParam !== '') {
            try {
                $websiteId = (int)$this->storeManager->getWebsite($websiteParam)->getId();
            } catch (Exception) {
                $websiteId = is_numeric($websiteParam) ? (int)$websiteParam : 0;
            }
        }

        $rows = $this->countryRateResolver->getRatesForExport($websiteId);

        $content = "Method Code,Country,Price\n";
        foreach ($rows as $row) {
            $content .= sprintf(
                "%s,%s,%.4f\n",
                $row['method_code'],
                $row['country_id'],
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
