<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Config\Source\PnpPackageType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Throwable;

class PnpReportService
{
    private const XML_PATH_SANDBOX = 'carriers/dhlparcel/sandbox';
    private const ALLOWED_PACKAGE_TYPES = [
        PnpPackageType::TYPE_EX,
        PnpPackageType::TYPE_DR,
        PnpPackageType::TYPE_ALL,
        PnpPackageType::TYPE_2EUROPE,
        PnpPackageType::TYPE_PR,
    ];

    /**
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Generate courier confirmation report (PnP) for a given date and package type.
     *
     * @param string $date Pickup date in YYYY-MM-DD format
     * @param string $typePackage Package type code (EX, DR, ALL, 2EUROPE, PR)
     *
     * @return array Array containing content, mimeType, and filename
     * @throws LocalizedException
     */
    public function generateReport(string $date, string $typePackage): array
    {
        $date = trim($date);
        $typePackage = strtoupper(trim($typePackage));

        if (empty($date)) {
            throw new LocalizedException(__('Invalid date format. Please specify date in YYYY-MM-DD format.'));
        }
        try {
            $dt = $this->timezone->date($date);
            $date = $dt->format('Y-m-d');
        } catch (Throwable) {
            throw new LocalizedException(__('Invalid date format. Please specify date in YYYY-MM-DD format.'));
        }

        if (!in_array($typePackage, self::ALLOWED_PACKAGE_TYPES, true)) {
            throw new LocalizedException(__('Invalid package type specified for courier report.'));
        }

        $credentials = $this->getCredentials();
        $result = $this->client->getPnp($credentials, $date, $typePackage);

        if (!$result->isSuccess()) {
            throw new LocalizedException(
                __('Failed to generate courier report: %1', $result->getErrorMessage() ?? __('Unknown API error'))
            );
        }

        $content = $result->getFileData();
        if (empty($content)) {
            throw new LocalizedException(__('No courier report data returned by DHL for date %1.', $date));
        }

        $filename = $result->getFileName() ?: sprintf('PNP_%s.pdf', $date);
        $mimeType = $result->getMimeType() ?: 'application/pdf';

        return [
            'content' => $content,
            'filename' => $filename,
            'mimeType' => $mimeType,
        ];
    }

    /**
     * Get API credentials based on carrier scope config.
     *
     * @return Credentials
     */
    private function getCredentials(): Credentials
    {
        $isSandbox = (bool) $this->scopeConfig->getValue(self::XML_PATH_SANDBOX);
        $environment = $isSandbox ? 'sandbox' : 'production';

        return $this->credentialsProvider->getCredentials($environment);
    }
}
