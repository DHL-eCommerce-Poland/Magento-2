<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ServicePoint;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class NearestPointResolver
{
    /**
     * @param CredentialsProvider $credentialsProvider
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ClientInterface $client,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve nearest DHL service point for recipient address if popId is not set.
     *
     * @param RecipientData $recipient
     * @param string $environment ('sandbox' or 'production')
     *
     * @return RecipientData Updated RecipientData with popId and popName filled
     * @throws LocalizedException
     */
    public function resolveForRecipient(
        RecipientData $recipient,
        string $environment = 'sandbox'
    ): RecipientData {
        $existingPopId = $recipient->getPopId();
        if ($existingPopId !== null && trim($existingPopId) !== '') {
            return $recipient;
        }

        $postalCode = trim($recipient->getPostalCode());
        $country = strtoupper(trim($recipient->getCountry())) ?: 'PL';
        $city = trim($recipient->getCity());

        if (empty($postalCode)) {
            throw new LocalizedException(
                __('Recipient postal code is required to find nearest DHL Service Point.')
            );
        }

        try {
            $credentials = $this->credentialsProvider->getCredentials($environment);
            $result = $this->client->getNearestServicepoints(
                $credentials,
                $country,
                $postalCode,
                $city,
                15
            );

            if ($result->isSuccess() && $result->getClosestPoint() !== null) {
                $closestPoint = $result->getClosestPoint();
                $sap = trim((string) ($closestPoint['sap'] ?? ''));
                $name = trim((string) ($closestPoint['name'] ?? ''));
                $street = trim((string) ($closestPoint['street'] ?? ''));
                $houseNumber = trim((string) ($closestPoint['houseNumber'] ?? ''));
                $pointPostcode = trim((string) ($closestPoint['postcode'] ?? ''));
                $pointCity = trim((string) ($closestPoint['city'] ?? ''));

                $streetStr = trim($street . ' ' . $houseNumber);
                $cityStr = trim($pointPostcode . ' ' . $pointCity);
                $addressParts = array_filter([$streetStr, $cityStr]);
                $fullAddressStr = implode(', ', $addressParts);

                $formattedPopName = $name;
                if (!empty($name) && !empty($fullAddressStr)) {
                    $formattedPopName = sprintf('%s - %s', $name, $fullAddressStr);
                } elseif (!empty($fullAddressStr)) {
                    $formattedPopName = $fullAddressStr;
                }

                if (!empty($sap)) {
                    $this->logger->info(sprintf(
                        '[DHL NearestPointResolver] Resolved nearest service point [%s - %s] for postal code %s, %s',
                        $sap,
                        $formattedPopName,
                        $postalCode,
                        $city
                    ));

                    $recipientArr = $recipient->toArray();
                    $recipientArr['popId'] = $sap;
                    $recipientArr['popName'] = $formattedPopName ?: $sap;

                    return RecipientData::fromArray($recipientArr);
                }
            }

            $errorMsg = $result->getErrorMessage() ?: 'No service point returned for location.';
            $this->logger->warning(sprintf(
                '[DHL NearestPointResolver] Failed to resolve nearest point for %s (%s): %s',
                $postalCode,
                $city,
                $errorMsg
            ));
        } catch (LocalizedException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[DHL NearestPointResolver] Exception resolving nearest point for %s: %s',
                $postalCode,
                $e->getMessage()
            ));
        }

        throw new LocalizedException(
            __('Could not resolve nearest DHL Service Point for postal code %1 (%2).', $postalCode, $city)
        );
    }
}
