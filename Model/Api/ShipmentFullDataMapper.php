<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

use Ideo\Dhl\Model\Config\Source\AddressType;
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
                'euroReturn' => $services->isEuroReturn(),
            ];
        }

        $deliveryHours = $services->getDeliveryHours();
        $isEvening = $services->isDeliveryEvening();
        $product = ($deliveryHours !== null && $deliveryHours !== '1722') ? $deliveryHours : $settings->getServiceType();

        $serviceDef = [
            'product' => $product,
            'deliveryEvening' => $isEvening,
            'pickupOnSaturday' => $services->isPickupOnSaturday(),
            'deliveryOnSaturday' => $services->isDeliveryOnSaturday(),
        ];

        if ($services->isCollectOnDelivery()) {
            $serviceDef['collectOnDelivery'] = true;
            $serviceDef['collectOnDeliveryValue'] = $services->getCollectOnDeliveryValue();
            $serviceDef['collectOnDeliveryForm'] = $services->getCollectOnDeliveryForm();
        }

        if ($services->isInsurance()) {
            $serviceDef['insurance'] = true;
            $serviceDef['insuranceValue'] = $services->getInsuranceValue();
        }

        if ($services->isPredeliveryInformation()) {
            $serviceDef['predeliveryInformation'] = true;
        }

        if ($services->isReturnOnDelivery()) {
            $serviceDef['returnOnDelivery'] = true;
            if ($services->getReturnOnDeliveryReference() !== '') {
                $serviceDef['returnOnDeliveryReference'] = $services->getReturnOnDeliveryReference();
            }
        }

        if ($services->isProofOfDelivery()) {
            $serviceDef['proofOfDelivery'] = true;
        }

        if ($services->isAgeVer()) {
            $serviceDef['ageVer'] = true;
        }

        if ($services->isSelfCollect()) {
            $serviceDef['selfCollect'] = true;
        }

        $senderCompanyName = trim($sender->getName());
        $senderFullName = trim($sender->getFirstName() . ' ' . $sender->getLastName());

        $senderName = $senderCompanyName !== '' ? $senderCompanyName : $senderFullName;
        $senderContact = $senderFullName;

        $recipientContact = $recipient->getContactPerson() !== ''
            ? $recipient->getContactPerson()
            : ($recipient->getFirstName() . ' ' . $recipient->getLastName());

        $payerType = match ($sender->getPayer()) {
            'RECIPIENT' => 'RECEIVER',
            'USER' => 'USER',
            default => 'SHIPPER',
        };

        $servicePointAccountNumber = !empty($recipient->getPopId())
            ? $this->sanitizeString($recipient->getPopId())
            : null;

        $rawType = strtoupper(trim($recipient->getType()));
        $receiverAddressType = in_array($rawType, [AddressType::TYPE_BUSINESS, AddressType::TYPE_CONSUMER], true)
            ? $rawType
            : (trim($recipient->getName()) !== '' ? AddressType::TYPE_BUSINESS : AddressType::TYPE_CONSUMER);

        $recipientName = $recipient->getName() !== ''
            ? $recipient->getName()
            : $recipientContact;

        $countryCode = strtoupper($recipient->getCountry() ?: 'PL');
        $senderCountryCode = strtoupper($sender->getCountry() ?: 'PL');

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

        if ($countryCode === 'DE') {
            if ($recipient->isPackstation()) {
                $receiverAddress['isPackstation'] = true;
            } elseif ($recipient->isPostfiliale()) {
                $receiverAddress['isPostfiliale'] = true;
            }

            if ($recipient->getPostnummer() !== null && $recipient->getPostnummer() !== '') {
                $receiverAddress['postnummer'] = $this->sanitizeString($recipient->getPostnummer());
            }
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
            'specialServices' => $serviceDef,
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
                'invoiceContent' => ($customsType === 'I') || $customs->isInvoiceContent(),
            ];

            $items = [];
            foreach ($customs->getCustomsItems() as $item) {
                if (is_array($item)) {
                    $items[] = [
                        'namePl' => mb_substr($this->sanitizeString((string)($item['namePl'] ?? '')), 0, 35),
                        'nameEn' => mb_substr($this->sanitizeString((string)($item['nameEn'] ?? '')), 0, 35),
                        'quantity' => (int)($item['quantity'] ?? 1),
                        'weight' => (float)($item['weight'] ?? 0.0),
                        'value' => (float)($item['value'] ?? 0.0),
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
