<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

use DateTimeImmutable;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\DhlShipmentRestrictions;

class ShipmentReturnDataMapper
{
    private const DEFAULT_EXPIRATION_DAYS = 14;

    /**
     * Map inverted DhlShipmentData into DHL WebAPI2 createShipmentReturn SOAP request array.
     *
     * @param DhlShipmentData $shipmentData
     * @param int $expirationDays Days until return label expires (default 14)
     * @param string|null $primaryWaybillNumber Primary shipment waybill if linking return
     *
     * @return array
     */
    public function map(
        DhlShipmentData $shipmentData,
        int $expirationDays = self::DEFAULT_EXPIRATION_DAYS,
        ?string $primaryWaybillNumber = null
    ): array {
        $customerSender = $shipmentData->getSender();
        $merchantRecipient = $shipmentData->getRecipient();
        $services = $shipmentData->getSpecialServices();
        $settings = $shipmentData->getShipmentSettings();
        $packages = $settings->getPackages();

        $pieceItems = [];
        foreach ($packages as $pkg) {
            $pieceItems[] = [
                'type' => $pkg->getPackageType(),
                'quantity' => $pkg->getQuantity(),
                'weight' => $pkg->getWeight(),
                'width' => $pkg->getWidth(),
                'height' => $pkg->getHeight(),
                'length' => $pkg->getLength(),
                'nonStandard' => $pkg->isNonStandard() || $services->isNonStandard(),
                'euroReturn' => $services->isEuroReturn(),
            ];
        }

        $customerContact = $customerSender->getName() !== ''
            ? $customerSender->getName()
            : ($customerSender->getFirstName() . ' ' . $customerSender->getLastName());

        $merchantContact = $merchantRecipient->getContactPerson() !== ''
            ? $merchantRecipient->getContactPerson()
            : ($merchantRecipient->getFirstName() . ' ' . $merchantRecipient->getLastName());

        $merchantName = $merchantRecipient->getName() !== ''
            ? $merchantRecipient->getName()
            : $merchantContact;

        $expDate = (new DateTimeImmutable())->modify('+' . max(1, $expirationDays) . ' days')->format('Y-m-d');

        $countryCode = strtoupper($merchantRecipient->getCountry() ?: 'PL');
        $serviceType = ($countryCode === 'PL') ? 'ZK' : 'ZC';

        $billingAccount = $customerSender->getSapNumber() !== ''
            ? $customerSender->getSapNumber()
            : $settings->getMpk();

        $billing = [
            'shippingPaymentType' => 'RECEIVER',
            'billingAccountNumber' => (int) $this->sanitizePostalCode($billingAccount ?: '0'),
            'paymentType' => 'BANK_TRANSFER',
        ];

        if ($settings->getMpk() !== '') {
            $billing['costsCenter'] = $this->sanitizeString($settings->getMpk());
        }

        $shipmentInfo = [
            'serviceType' => $serviceType,
            'bookCourier' => false,
            'billing' => $billing,
            'shipmentTime' => [
                'labelExpDate' => $expDate,
            ],
            'labelType' => $settings->getLabelType() ?: 'BLP',
        ];

        $specialServices = [];
        if ($services->isInsurance()) {
            $specialServices[] = [
                'serviceType' => 'UBEZP',
                'serviceValue' => $services->getInsuranceValue(),
            ];
        }

        if (!empty($specialServices)) {
            $shipmentInfo['specialServices'] = [
                'item' => $specialServices,
            ];
        }

        $customerCountry = strtoupper($customerSender->getCountry() ?: 'PL');

        $result = [
            'shipmentInfo' => $shipmentInfo,
            'content' => $this->truncateString(
                $this->sanitizeString($settings->getContent() ?: __('Zwrot zamówienia')),
                DhlShipmentRestrictions::MAX_LENGTH_CONTENT
            ),
            'ship' => [
                'shipper' => [
                    'address' => [
                        'country' => $customerCountry,
                        'name' => $this->sanitizeString($customerSender->getName() ?: $customerContact),
                        'postalCode' => $this->sanitizePostalCode($customerSender->getPostalCode()),
                        'city' => $this->sanitizeString($customerSender->getCity()),
                        'street' => $this->sanitizeString($customerSender->getStreet()),
                        'houseNumber' => $this->sanitizeString($customerSender->getHouseNumber()),
                        'apartmentNumber' => $this->sanitizeString($customerSender->getFlatNumber()),
                    ],
                    'contact' => [
                        'personName' => $this->sanitizeString($customerContact),
                        'phoneNumber' => $this->sanitizeString($customerSender->getPhone()),
                        'emailAddress' => $this->sanitizeString($customerSender->getEmail()),
                    ],
                ],
                'receiver' => [
                    'address' => [
                        'country' => $countryCode,
                        'name' => $this->sanitizeString($merchantName),
                        'postalCode' => $this->sanitizePostalCode($merchantRecipient->getPostalCode()),
                        'city' => $this->sanitizeString($merchantRecipient->getCity()),
                        'street' => $this->sanitizeString($merchantRecipient->getStreet()),
                        'houseNumber' => $this->sanitizeString($merchantRecipient->getHouseNumber()),
                        'apartmentNumber' => $this->sanitizeString($merchantRecipient->getFlatNumber()),
                    ],
                    'contact' => [
                        'personName' => $this->sanitizeString($merchantContact),
                        'phoneNumber' => $this->sanitizeString($merchantRecipient->getPhone()),
                        'emailAddress' => $this->sanitizeString($merchantRecipient->getEmail()),
                    ],
                ],
            ],
            'pieceList' => [
                'item' => $pieceItems,
            ],
        ];

        if ($settings->getComment() !== '') {
            $result['comment'] = $this->truncateString(
                $this->sanitizeString($settings->getComment()),
                DhlShipmentRestrictions::MAX_LENGTH_COMMENT
            );
        }

        if ($settings->getReference() !== '') {
            $result['reference'] = $this->truncateString(
                $this->sanitizeString($settings->getReference()),
                DhlShipmentRestrictions::MAX_LENGTH_REFERENCE
            );
        }

        if (!empty($primaryWaybillNumber)) {
            $result['primaryWaybillNumber'] = $this->sanitizeString($primaryWaybillNumber);
        }

        return $result;
    }

    /**
     * Sanitize string by removing control characters.
     *
     * @param string|null $string
     *
     * @return string
     */
    public function sanitizeString(?string $string): string
    {
        if ($string === null || $string === '') {
            return '';
        }

        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim($string));
    }

    /**
     * Format postal code to 5 digits without hyphens.
     *
     * @param string $postalCode
     *
     * @return string
     */
    public function sanitizePostalCode(string $postalCode): string
    {
        return (string) preg_replace('/[^0-9]/', '', $postalCode);
    }

    /**
     * Safely truncate UTF-8 string to maximum character length.
     *
     * @param string $string
     * @param int $maxLength
     *
     * @return string
     */
    public function truncateString(string $string, int $maxLength): string
    {
        if (mb_strlen($string) <= $maxLength) {
            return $string;
        }

        return mb_substr($string, 0, $maxLength);
    }
}
