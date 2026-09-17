<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Api;

use Ideo\Dhl\Model\Config\Source\AddressType;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\DhlShipmentRestrictions;

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

        $serviceDef = [
            'product' => $services->getDeliveryHours() ?? $settings->getServiceType(),
            'deliveryEvening' => $services->getDeliveryHours() === '1722',
            'pickupOnSaturday' => $services->isSaturdayDispatch(),
            'deliveryOnSaturday' => $services->isSaturdayDelivery(),
        ];

        if ($services->isCod()) {
            $serviceDef['collectOnDelivery'] = true;
            $serviceDef['collectOnDeliveryValue'] = $services->getCodAmount();
            $serviceDef['collectOnDeliveryForm'] = $services->getCodPaymentMethod();
        }

        if ($services->isInsurance()) {
            $serviceDef['insurance'] = true;
            $serviceDef['insuranceValue'] = $services->getInsuranceValue();
        }

        if ($services->isPdi()) {
            $serviceDef['predeliveryInformation'] = true;
            $serviceDef['preaviso'] = true;
        }

        if ($services->isRod()) {
            $serviceDef['returnOnDelivery'] = true;
            if ($services->getRodDocumentText() !== '') {
                $serviceDef['returnOnDeliveryReference'] = $services->getRodDocumentText();
            }
        }

        if ($services->isPod()) {
            $serviceDef['proofOfDelivery'] = true;
        }

        $senderContact = $sender->getName() !== ''
            ? $sender->getName()
            : ($sender->getFirstName() . ' ' . $sender->getLastName());

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

        $receiver = [
            'addressType' => $receiverAddressType,
            'country' => $countryCode,
            'name' => $this->sanitizeString($recipientName),
            'postalCode' => $this->sanitizePostalCode($recipient->getPostalCode()),
            'city' => $this->sanitizeString($recipient->getCity()),
            'street' => $this->sanitizeString($recipient->getStreet()),
            'houseNumber' => $this->sanitizeString($recipient->getHouseNumber()),
            'apartmentNumber' => $this->sanitizeString($recipient->getFlatNumber()),
            'contactPerson' => $this->sanitizeString($recipientContact),
            'contactPhone' => $this->sanitizeString($recipient->getPhone()),
            'contactEmail' => $this->sanitizeString($recipient->getEmail()),
        ];

        if ($countryCode === 'DE') {
            if ($recipient->isPackstation()) {
                $receiver['isPackstation'] = true;
            } else {
                $receiver['isPostfiliale'] = true;
            }

            if ($recipient->getPostnummer() !== null && $recipient->getPostnummer() !== '') {
                $receiver['postnummer'] = $this->sanitizeString($recipient->getPostnummer());
            }
        } elseif ($recipient->isPostfiliale()) {
            $receiver['isPostfiliale'] = true;
        }

        $result = [
            'shipper' => [
                'name' => $this->sanitizeString($sender->getName()),
                'postalCode' => $this->sanitizePostalCode($sender->getPostalCode()),
                'city' => $this->sanitizeString($sender->getCity()),
                'street' => $this->sanitizeString($sender->getStreet()),
                'houseNumber' => $this->sanitizeString($sender->getHouseNumber()),
                'apartmentNumber' => $this->sanitizeString($sender->getFlatNumber()),
                'contactPerson' => $this->sanitizeString($senderContact),
                'contactPhone' => $this->sanitizeString($sender->getPhone()),
                'contactEmail' => $this->sanitizeString($sender->getEmail()),
            ],
            'receiver' => $receiver,
            'pieceList' => [
                'item' => $pieceItems,
            ],
            'payment' => [
                'paymentMethod' => 'BANK_TRANSFER',
                'payerType' => $payerType,
                'accountNumber' => $this->sanitizeString($sender->getSapNumber()),
                'costsCenter' => $this->sanitizeString($settings->getMpk()),
            ],
            'service' => $serviceDef,
            'shipmentDate' => $settings->getShipmentDate(),
            'skipRestrictionCheck' => false,
            'servicePointAccountNumber' => $servicePointAccountNumber,
            'content' => $this->truncateString(
                $this->sanitizeString($settings->getContent()),
                DhlShipmentRestrictions::MAX_LENGTH_CONTENT
            ),
            'comment' => $this->truncateString(
                $this->sanitizeString($settings->getComment()),
                DhlShipmentRestrictions::MAX_LENGTH_COMMENT
            ),
            'reference' => $this->truncateString(
                $this->sanitizeString($settings->getReference()),
                DhlShipmentRestrictions::MAX_LENGTH_REFERENCE
            ),
        ];

        if ($settings->getPickupHourFrom() !== null && $settings->getPickupHourFrom() !== '') {
            $result['shipmentStartHour'] = $settings->getPickupHourFrom();
        }
        if ($settings->getPickupHourTo() !== null && $settings->getPickupHourTo() !== '') {
            $result['shipmentEndHour'] = $settings->getPickupHourTo();
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
