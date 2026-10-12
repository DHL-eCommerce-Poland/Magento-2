<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use DhlPoland\Dhl\Model\Config\Source\PnpPackageType;
use Magento\Framework\Exception\LocalizedException;

class PnpReportService
{
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
     * @param ShipmentDateSanitizer $dateSanitizer
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ShipmentDateSanitizer $dateSanitizer
    ) {
    }

    /**
     * Generate courier confirmation report (PnP) for a given date and package type.
     *
     * @param string $date Pickup date in YYYY-MM-DD format
     * @param string $typePackage
     *
     * @return array
     * @throws LocalizedException
     */
    public function generateReport(string $date, string $typePackage): array
    {
        $date = trim($date);
        $typePackage = strtoupper(trim($typePackage));

        if (empty($date)) {
            throw new LocalizedException(__('Invalid date format. Please specify date in YYYY-MM-DD format.'));
        }
        $sanitizedDate = $this->dateSanitizer->sanitize($date, 'INVALID', true);
        if ($sanitizedDate === 'INVALID') {
            throw new LocalizedException(__('Invalid date format. Please specify date in YYYY-MM-DD format.'));
        }
        $date = $sanitizedDate;

        if (!in_array($typePackage, self::ALLOWED_PACKAGE_TYPES, true)) {
            throw new LocalizedException(__('Invalid package type specified for courier report.'));
        }

        $credentials = $this->credentialsProvider->getCredentials();
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
}
