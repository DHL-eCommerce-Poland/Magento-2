<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

use Ideo\Dhl\Model\Config\Source\ReturnBookCourier;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class ShipmentReturnDataMapper
{
    private const DEFAULT_EXPIRATION_DAYS = 14;

    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

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

        $effectiveExpDays = $settings->getReturnLabelExpDays() ?: $expirationDays;

        $expDate = $this->timezone->scopeDate()
            ->modify('+' . max(1, $effectiveExpDays) . ' days')
            ->format('Y-m-d');

        $countryCode = strtoupper($merchantRecipient->getCountry() ?: 'PL');
        $serviceType = ($countryCode === 'PL') ? 'ZK' : 'ZC';

        $billingAccount = $customerSender->getSapNumber() !== ''
            ? $customerSender->getSapNumber()
            : $settings->getMpk();

        $billing = [
            'shippingPaymentType' => 'RECEIVER',
            'billingAccountNumber' => (int) preg_replace('/[^0-9]/', '', $billingAccount ?: '0'),
            'paymentType' => 'BANK_TRANSFER',
        ];

        if ($settings->getMpk() !== '') {
            $billing['costsCenter'] = $this->sanitizeString($settings->getMpk());
        }

        $returnBookCourierVal = (string) ($settings->getReturnBookCourier() ?? ReturnBookCourier::DROP_OFF_OR_COURIER);
        $bookCourier = $returnBookCourierVal === ReturnBookCourier::DROP_OFF_OR_COURIER;
        $labelType = $settings->getReturnLabelType() ?: ($settings->getLabelType() ?: 'BLP');

        $shipmentInfo = [
            'serviceType' => $serviceType,
            'bookCourier' => $bookCourier,
            'billing' => $billing,
            'shipmentTime' => [
                'labelExpDate' => $expDate,
            ],
            'labelType' => $labelType,
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
            'content' => $this->sanitizeString($settings->getContent() ?: __('Order Return')),
            'ship' => [
                'shipper' => [
                    'address' => [
                        'country' => $customerCountry,
                        'name' => $this->sanitizeString($customerSender->getName() ?: $customerContact),
                        'postalCode' => $this->sanitizePostalCode($customerSender->getPostalCode(), $customerCountry),
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
                        'postalCode' => $this->sanitizePostalCode($merchantRecipient->getPostalCode(), $countryCode),
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
            $result['comment'] = $this->sanitizeString($settings->getComment());
        }

        if ($settings->getReference() !== '') {
            $result['reference'] = $this->sanitizeString($settings->getReference());
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
     * Format postal code: remove hyphen for PL, return unchanged for international countries.
     *
     * @param string $postalCode
     * @param string $countryCode
     *
     * @return string
     */
    public function sanitizePostalCode(string $postalCode, string $countryCode = 'PL'): string
    {
        $postalCode = trim($postalCode);
        if (strtoupper($countryCode) === 'PL') {
            return str_replace('-', '', $postalCode);
        }

        return $postalCode;
    }
}
