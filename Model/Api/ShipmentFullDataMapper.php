<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

use Ideo\Dhl\Model\Config\Source\AddressType;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;

class ShipmentFullDataMapper
{
    /**
     * Map DhlShipmentData to DHL WebAPI2 ShipmentFullData array format.
     *
     * @param DhlShipmentData $shipmentData
     *
     * @return array
     */
    public function map(DhlShipmentData $shipmentData): array
    {
        $sender = $shipmentData->getSender();
        $recipient = $shipmentData->getRecipient();
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

        $deliveryHours = $services->getDeliveryHours();
        $isEvening = $services->isDeliveryEvening();
        $product = ($deliveryHours !== null && $deliveryHours !== '1722')
            ? $deliveryHours
            : $settings->getServiceType();

        $specialServices = $this->mapSpecialServices($services);

        $senderCompanyName = trim($sender->getName());
        $senderFullName = trim($sender->getFirstName() . ' ' . $sender->getLastName());

        $senderName = $senderCompanyName !== '' ? $senderCompanyName : $senderFullName;
        $senderContact = $senderFullName;

        $recipientFullName = trim($recipient->getFirstName() . ' ' . $recipient->getLastName());
        $recipientContact = $recipientFullName !== ''
            ? $recipientFullName
            : $recipient->getContactPerson();

        $payerType = match ($sender->getPayer()) {
            'RECIPIENT' => 'RECEIVER',
            'USER' => 'USER',
            default => 'SHIPPER',
        };

        $serviceTypeUpper = strtoupper(trim((string)$product));
        $isPointService = in_array(
            $serviceTypeUpper,
            [MethodCatalog::SERVICE_SP, MethodCatalog::SERVICE_EK],
            true
        );

        $countryCode = strtoupper($recipient->getCountry() ?: 'PL');
        $senderCountryCode = strtoupper($sender->getCountry() ?: 'PL');

        $isPointDelivery = false;
        if ($isPointService) {
            if ($countryCode === 'PL') {
                $isPointDelivery = !empty($recipient->getPopId());
            } else {
                $isPointDelivery = $recipient->isPackstation() || $recipient->isPostfiliale();
            }
        }

        $servicePointAccountNumber = ($countryCode === 'PL' && $isPointDelivery && !empty($recipient->getPopId()))
            ? $this->sanitizeString($recipient->getPopId())
            : null;

        $rawType = strtoupper(trim($recipient->getType()));
        $receiverAddressType = in_array($rawType, [AddressType::TYPE_BUSINESS, AddressType::TYPE_CONSUMER], true)
            ? $rawType
            : (trim($recipient->getName()) !== '' ? AddressType::TYPE_BUSINESS : AddressType::TYPE_CONSUMER);

        $recipientName = $recipient->getName() !== ''
            ? $recipient->getName()
            : $recipientContact;

        $senderAddress = [
            'name' => $this->sanitizeString($senderName),
            'postalCode' => $this->sanitizePostalCode($sender->getPostalCode(), $senderCountryCode),
            'city' => $this->sanitizeString($sender->getCity()),
            'street' => $this->sanitizeString($sender->getStreet()),
            'houseNumber' => $this->sanitizeString($sender->getHouseNumber()),
            'apartmentNumber' => $this->sanitizeString($sender->getFlatNumber()),
        ];

        $senderContactInfo = [
            'personName' => $this->sanitizeString($senderContact),
            'phoneNumber' => $this->sanitizeString($sender->getPhone()),
            'emailAddress' => $this->sanitizeString($sender->getEmail()),
        ];

        if ($isPointDelivery && $countryCode !== 'PL') {
            $popStreet = trim((string)$recipient->getPopStreet());
            $popCity = trim((string)$recipient->getPopCity());
            $popPostcode = trim((string)$recipient->getPopPostcode());
            $popName = trim((string)$recipient->getPopName());

            if ($popName !== '') {
                $parsed = $this->parsePointAddressFromName($popName);
                $isLongerStreet = str_contains($parsed['street'], $popStreet)
                    && strlen($parsed['street']) > strlen($popStreet);
                if (!empty($parsed['street']) && ($popStreet === '' || $isLongerStreet)) {
                    $popStreet = $parsed['street'];
                }
                if ($popPostcode === '' && !empty($parsed['postcode'])) {
                    $popPostcode = $parsed['postcode'];
                }
                if ($popCity === '' && !empty($parsed['city'])) {
                    $popCity = $parsed['city'];
                }
            }

            $pointStreet = $popStreet !== '' ? $popStreet : $recipient->getStreet();
            $pointCity = $popCity !== '' ? $popCity : $recipient->getCity();
            $pointPostcode = $popPostcode !== '' ? $popPostcode : $recipient->getPostalCode();
            $pointHouseNumber = $recipient->getPopId() ?: $recipient->getHouseNumber();

            $receiverAddress = [
                'addressType' => AddressType::TYPE_CONSUMER,
                'country' => $countryCode,
                'name' => $this->sanitizeString($recipientName),
                'postalCode' => $this->sanitizePostalCode($pointPostcode, $countryCode),
                'city' => $this->sanitizeString($pointCity),
                'street' => $this->sanitizeString($pointStreet),
                'houseNumber' => $this->sanitizeString($pointHouseNumber),
                'apartmentNumber' => '',
            ];

            if ($recipient->isPackstation()) {
                $receiverAddress['isPackstation'] = true;
            } elseif ($recipient->isPostfiliale()) {
                $receiverAddress['isPostfiliale'] = true;
            }

            if ($countryCode === 'DE' && $recipient->getPostnummer() !== null && $recipient->getPostnummer() !== '') {
                $receiverAddress['postnummer'] = $this->sanitizeString($recipient->getPostnummer());
            }
        } else {
            $receiverAddress = [
                'addressType' => $receiverAddressType,
                'country' => $countryCode,
                'name' => $this->sanitizeString($recipientName),
                'postalCode' => $this->sanitizePostalCode($recipient->getPostalCode(), $countryCode),
                'city' => $this->sanitizeString($recipient->getCity()),
                'street' => $this->sanitizeString($recipient->getStreet()),
                'houseNumber' => $this->sanitizeString($recipient->getHouseNumber()),
                'apartmentNumber' => $this->sanitizeString($recipient->getFlatNumber()),
            ];
        }

        $recipientContactInfo = [
            'personName' => $this->sanitizeString($recipientContact),
            'phoneNumber' => $this->sanitizeString($recipient->getPhone()),
            'emailAddress' => $this->sanitizeString($recipient->getEmail()),
        ];

        $shipmentTime = [
            'shipmentDate' => $settings->getShipmentDate(),
        ];
        if ($settings->getPickupHourFrom() !== null && $settings->getPickupHourFrom() !== '') {
            $shipmentTime['shipmentStartHour'] = $settings->getPickupHourFrom();
        }
        if ($settings->getPickupHourTo() !== null && $settings->getPickupHourTo() !== '') {
            $shipmentTime['shipmentEndHour'] = $settings->getPickupHourTo();
        }

        $shipInfo = [
            'dropOffType' => CreateShipmentResult::DROP_OFF_TYPE_REGULAR_PICKUP,
            'serviceType' => $product,
            'billing' => [
                'shippingPaymentType' => $payerType,
                'billingAccountNumber' => $this->sanitizeString($sender->getSapNumber()),
                'paymentType' => SpecialServicesData::PAYMENT_METHOD_BANK_TRANSFER,
                'costsCenter' => $this->sanitizeString($settings->getMpk()),
            ],
            'specialServices' => $specialServices,
            'shipmentTime' => $shipmentTime,
            'labelType' => $settings->getLabelType(),
        ];

        $ship = [
            'servicePointAccountNumber' => $servicePointAccountNumber,
            'shipper' => [
                'preaviso' => $senderContactInfo,
                'contact' => $senderContactInfo,
                'address' => $senderAddress,
            ],
            'receiver' => [
                'preaviso' => $recipientContactInfo,
                'contact' => $recipientContactInfo,
                'address' => $receiverAddress,
            ],
        ];

        $result = [
            'shipmentInfo' => $shipInfo,
            'content' => $this->sanitizeString($settings->getContent()),
            'comment' => $this->sanitizeString($settings->getComment()),
            'reference' => $this->sanitizeString($settings->getReference()),
            'ship' => $ship,
            'pieceList' => [
                'item' => $pieceItems,
            ],
        ];

        if ($countryCode !== 'PL'
            && $shipmentData->getCustoms() !== null
        ) {
            $customs = $shipmentData->getCustoms();
            $customsType = $customs->getCustomsType() ?: 'U';

            $customsDef = [
                'customsType' => $customsType,
                'costsOfShipment' => (int)round((float)($customs->getCostsOfShipment() ?? 0)),
                'currency' => $customs->getCurrency() ?: 'EUR',
                'categoryOfItem' => $customs->getCategoryOfItem() ?: '11',
                'countryOfOrigin' => $customs->getCountryOfOrigin() ?: 'PL',
                'grossWeight' => (float)($customs->getGrossWeight() ?? 0.0),
                'firstName' => mb_substr($this->sanitizeString($customs->getFirstName() ?? ''), 0, 30),
                'secondaryName' => mb_substr($this->sanitizeString($customs->getSecondaryName() ?? ''), 0, 30),
                'vatRegistrationNumber' => mb_substr(
                    $this->sanitizeString($customs->getVatRegistrationNumber() ?? ''),
                    0,
                    15
                ),
            ];

            if ($customs->getNipNr()) {
                $customsDef['nipNr'] = mb_substr($this->sanitizeString($customs->getNipNr()), 0, 17);
            }
            if ($customs->getEoriNr()) {
                $customsDef['eoriNr'] = mb_substr($this->sanitizeString($customs->getEoriNr()), 0, 17);
            }
            if ($customs->getEoriNrReceiver()) {
                $customsDef['eoriNrReceiver'] = mb_substr($this->sanitizeString($customs->getEoriNrReceiver()), 0, 17);
            }
            if ($customs->getVatRegistrationNumberReceiver()) {
                $customsDef['vatRegistrationNumberReceiver'] = mb_substr(
                    $this->sanitizeString($customs->getVatRegistrationNumberReceiver()),
                    0,
                    15
                );
            }
            if ($customs->getAdditionalInfo()) {
                $customsDef['additionalInfo'] = mb_substr(
                    $this->sanitizeString($customs->getAdditionalInfo()),
                    0,
                    100
                );
            }

            if ($customsType === 'I') {
                if ($customs->getInvoiceNr()) {
                    $customsDef['invoiceNr'] = mb_substr($this->sanitizeString($customs->getInvoiceNr()), 0, 35);
                }
                if ($customs->getInvoiceDate()) {
                    $customsDef['invoiceDate'] = $customs->getInvoiceDate();
                }
                if ($customs->getInvoiceFile()) {
                    $rawInvoice = $customs->getInvoiceFile();
                    $customsDef['invoice'] = (string) preg_replace('/^data:[^;]+;base64,/', '', $rawInvoice);
                }
            }

            $customsDef['customAgreements'] = [
                'notExceedValue' => $customs->isNotExceedValue(),
                'notProhibitedGoods' => $customs->isNotProhibitedGoods(),
                'notRestrictedGoods' => $customs->isNotRestrictedGoods(),
                'invoiceContent' => $customsType === 'I' && $customs->isInvoiceContent(),
            ];

            $items = [];
            foreach ($customs->getCustomsItems() as $item) {
                if (is_array($item)) {
                    $items[] = [
                        'namePl' => mb_substr($this->sanitizeString((string)($item['namePl'] ?? '')), 0, 35),
                        'nameEn' => mb_substr($this->sanitizeString((string)($item['nameEn'] ?? '')), 0, 35),
                        'quantity' => isset($item['quantity']) && trim((string)$item['quantity']) !== ''
                            ? (int)str_replace(',', '.', trim((string)$item['quantity']))
                            : '',
                        'weight' => isset($item['weight']) && trim((string)$item['weight']) !== ''
                            ? (float)str_replace(',', '.', trim((string)$item['weight']))
                            : '',
                        'value' => isset($item['value']) && trim((string)$item['value']) !== ''
                            ? (float)str_replace(',', '.', trim((string)$item['value']))
                            : '',
                        'tariffCode' => mb_substr($this->sanitizeString((string)($item['tariffCode'] ?? '')), 0, 10),
                        'countryOfOrigin' => mb_substr(
                            $this->sanitizeString((string)($item['countryOfOrigin'] ?? '')),
                            0,
                            35
                        ),
                    ];
                }
            }
            $customsDef['customsItem'] = $items;

            $result['customs'] = $customsDef;
        }

        return $result;
    }

    /**
     * Sanitize string by removing non-printable XML/ASCII control characters (< 32 except tabs/newlines).
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
     * Remove spaces from postal codes and hyphens from Polish postal codes for DHL.
     *
     * @param string $postalCode
     * @param string $countryCode
     *
     * @return string
     */
    public function sanitizePostalCode(string $postalCode, string $countryCode = 'PL'): string
    {
        $postalCode = str_replace(' ', '', trim($postalCode));
        if (strtoupper($countryCode) === 'PL') {
            return str_replace('-', '', $postalCode);
        }

        return $postalCode;
    }

    /**
     * Parse point street, postcode, and city from a combined popName string.
     *
     * @param string $popName
     *
     * @return array{street: string, postcode: string, city: string}
     */
    private function parsePointAddressFromName(string $popName): array
    {
        $result = ['street' => '', 'postcode' => '', 'city' => ''];
        if (trim($popName) === '') {
            return $result;
        }

        $shopName = '';
        $addressPart = $popName;
        if (str_contains($popName, ' - ')) {
            $parts = explode(' - ', $popName, 2);
            $shopName = trim($parts[0]);
            $addressPart = trim($parts[1]);
        }

        if (preg_match('/\b(\d{4,5})\b/', $addressPart, $matches, PREG_OFFSET_CAPTURE)) {
            $postcode = $matches[1][0];
            $postcodePos = (int)$matches[1][1];
            $result['postcode'] = $postcode;

            $afterPostcode = trim(substr($addressPart, $postcodePos + strlen($postcode)));
            if ($afterPostcode !== '') {
                $result['city'] = trim($afterPostcode, " \t\n\r\0\x0B,");
            }

            $beforePostcode = trim(substr($addressPart, 0, $postcodePos));
            $streetPart = trim($beforePostcode, " \t\n\r\0\x0B,");
            $isGenericPrefix = (bool)preg_match('/^(packstation|postfiliale)\b/i', $shopName);
            $includeShopName = $shopName !== '' && !$isGenericPrefix && $streetPart !== '' && $shopName !== $streetPart;
            $result['street'] = $includeShopName
                ? sprintf('%s - %s', $shopName, $streetPart)
                : ($streetPart ?: $shopName);
        } elseif (str_contains($addressPart, ',')) {
            $parts = array_map('trim', explode(',', $addressPart, 2));
            $streetPart = $parts[0];
            $isGenericPrefix = (bool)preg_match('/^(packstation|postfiliale)\b/i', $shopName);
            $includeShopName = $shopName !== '' && !$isGenericPrefix && $streetPart !== '' && $shopName !== $streetPart;
            $result['street'] = $includeShopName
                ? sprintf('%s - %s', $shopName, $streetPart)
                : ($streetPart ?: $shopName);
            if (!empty($parts[1])) {
                $result['city'] = $parts[1];
            }
        }

        return $result;
    }

    /**
     * Map special services to DHL WebAPI2 Service structures.
     *
     * @param SpecialServicesData $services
     *
     * @return array
     */
    private function mapSpecialServices(SpecialServicesData $services): array
    {
        $items = [];

        if ($services->isInsurance()) {
            $items[] = [
                'serviceType' => 'UBEZP',
                'serviceValue' => $services->getInsuranceValue(),
            ];
        }

        if ($services->isCollectOnDelivery()) {
            $items[] = [
                'serviceType' => 'COD',
                'serviceValue' => $services->getCollectOnDeliveryValue(),
                'collectOnDeliveryForm' => $services->getCollectOnDeliveryForm()
                    ?: SpecialServicesData::PAYMENT_METHOD_BANK_TRANSFER,
            ];
        }

        if ($services->isReturnOnDelivery()) {
            $rodItem = ['serviceType' => 'ROD'];
            if ($services->getReturnOnDeliveryReference() !== '') {
                $rodItem['textInstruction'] = mb_substr($services->getReturnOnDeliveryReference(), 0, 32);
            }
            $items[] = $rodItem;
        }

        $flagServices = [
            '1722' => $services->isDeliveryEvening(),
            'SOBOTA' => $services->isDeliveryOnSaturday(),
            'NAD_SOBOTA' => $services->isPickupOnSaturday(),
            'PDI' => $services->isPredeliveryInformation(),
            'POD' => $services->isProofOfDelivery(),
            'AGEVER' => $services->isAgeVer(),
        ];

        foreach ($flagServices as $code => $isEnabled) {
            if ($isEnabled) {
                $items[] = ['serviceType' => $code];
            }
        }

        return !empty($items) ? ['item' => $items] : [];
    }
}
