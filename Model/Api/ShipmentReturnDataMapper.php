<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Api;

use DhlPoland\Dhl\Model\Config\Source\ReturnBookCourier;
use DhlPoland\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use DhlPoland\Dhl\Model\Shipment\Data\SpecialServicesData;
use DhlPoland\Dhl\Model\Shipment\DhlShipmentData;
use DhlPoland\Dhl\Model\Shipment\PostalCodeSanitizer;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class ShipmentReturnDataMapper
{
    /**
     * @param TimezoneInterface $timezone
     * @param PostalCodeSanitizer $postalCodeSanitizer
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly PostalCodeSanitizer $postalCodeSanitizer
    ) {
    }

    /**
     * Map inverted DhlShipmentData into DHL WebAPI2 createShipmentReturn SOAP request array.
     *
     * @param DhlShipmentData $shipmentData
     * @param int $expirationDays Days until return label expires
     * @param string|null $primaryWaybillNumber Primary shipment waybill if linking return
     *
     * @return array
     */
    public function map(
        DhlShipmentData $shipmentData,
        int $expirationDays = ShipmentSettingsData::DEFAULT_RETURN_LABEL_EXP_DAYS,
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
                'euroReturn' => $pkg->hasEuroReturn(),
            ];
        }

        $customerCompanyName = trim($customerSender->getName());
        $customerFullName = trim($customerSender->getFirstName() . ' ' . $customerSender->getLastName());

        $customerName = $customerCompanyName !== '' ? $customerCompanyName : $customerFullName;
        $customerContact = $customerFullName !== '' ? $customerFullName : $customerCompanyName;

        $merchantContact = $merchantRecipient->getContactPerson() !== ''
            ? $merchantRecipient->getContactPerson()
            : trim($merchantRecipient->getFirstName() . ' ' . $merchantRecipient->getLastName());

        if ($merchantContact === '') {
            $merchantContact = $merchantRecipient->getName();
        }

        $merchantName = $merchantRecipient->getName() !== ''
            ? $merchantRecipient->getName()
            : $merchantContact;

        $effectiveExpDays = $settings->getReturnLabelExpDays() ?: $expirationDays;

        $expDate = $this->timezone->scopeDate()
            ->modify('+' . max(1, $effectiveExpDays) . ' days')
            ->format('Y-m-d');

        $countryCode = strtoupper($merchantRecipient->getCountry() ?: 'PL');
        $customerCountry = strtoupper($customerSender->getCountry() ?: 'PL');
        $configuredServiceType = strtoupper(trim((string) $settings->getServiceType()));
        $serviceType = ($customerCountry !== 'PL' || $configuredServiceType === 'ZC') ? 'ZC' : 'ZK';

        $billingAccount = $customerSender->getSapNumber() !== ''
            ? $customerSender->getSapNumber()
            : $settings->getMpk();

        $billing = [
            'shippingPaymentType' => 'RECEIVER',
            'billingAccountNumber' => (int) preg_replace('/[^0-9]/', '', $billingAccount ?: '0'),
            'paymentType' => SpecialServicesData::PAYMENT_METHOD_BANK_TRANSFER,
        ];

        if ($settings->getMpk() !== '') {
            $billing['costsCenter'] = $this->sanitizeString($settings->getMpk());
        }

        if ($serviceType === 'ZC') {
            $bookCourier = false;
        } else {
            $returnBookCourierVal = (string) (
                $settings->getReturnBookCourier() ?? ReturnBookCourier::DROP_OFF_OR_COURIER
            );
            $bookCourier = $returnBookCourierVal === ReturnBookCourier::DROP_OFF_OR_COURIER;
        }
        $labelType = $settings->getReturnLabelType() ?: 'BLP';

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

        $customerContactInfo = [
            'personName' => $this->sanitizeString($customerContact),
            'phoneNumber' => $this->sanitizeString($customerSender->getPhone()),
            'emailAddress' => $this->sanitizeString($customerSender->getEmail()),
        ];

        $merchantContactInfo = [
            'personName' => $this->sanitizeString($merchantContact),
            'phoneNumber' => $this->sanitizeString($merchantRecipient->getPhone()),
            'emailAddress' => $this->sanitizeString($merchantRecipient->getEmail()),
        ];

        $customerPostalCode = $this->postalCodeSanitizer->sanitize(
            $customerSender->getPostalCode(),
            $customerCountry
        );
        $merchantPostalCode = $this->postalCodeSanitizer->sanitize(
            $merchantRecipient->getPostalCode(),
            $countryCode
        );

        $result = [
            'shipmentInfo' => $shipmentInfo,
            'content' => $this->sanitizeString($settings->getContent()),
            'ship' => [
                'shipper' => [
                    'preaviso' => $customerContactInfo,
                    'contact' => $customerContactInfo,
                    'address' => [
                        'country' => $customerCountry,
                        'name' => $this->sanitizeString($customerName),
                        'postalCode' => $customerPostalCode,
                        'city' => $this->sanitizeString($customerSender->getCity()),
                        'street' => $this->sanitizeString($customerSender->getStreet()),
                        'houseNumber' => $this->sanitizeString($customerSender->getHouseNumber()),
                        'apartmentNumber' => $this->sanitizeString($customerSender->getFlatNumber()),
                    ],
                ],
                'receiver' => [
                    'preaviso' => $merchantContactInfo,
                    'contact' => $merchantContactInfo,
                    'address' => [
                        'country' => $countryCode,
                        'name' => $this->sanitizeString($merchantName),
                        'postalCode' => $merchantPostalCode,
                        'city' => $this->sanitizeString($merchantRecipient->getCity()),
                        'street' => $this->sanitizeString($merchantRecipient->getStreet()),
                        'houseNumber' => $this->sanitizeString($merchantRecipient->getHouseNumber()),
                        'apartmentNumber' => $this->sanitizeString($merchantRecipient->getFlatNumber()),
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
}
