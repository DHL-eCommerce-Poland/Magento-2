<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\Shipment\Data\RecipientData;
use Ideo\Dhl\Model\Shipment\Data\SenderData;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\Data\SpecialServicesData;

class ReturnShipmentDataBuilder
{
    /**
     * Build inverted DhlShipmentData for return shipment from original outgoing DhlShipmentData.
     *
     * Inverts Sender (now Customer) and Recipient (now Merchant/Warehouse).
     *
     * @param DhlShipmentData $originalShipmentData
     *
     * @return DhlShipmentData
     */
    public function buildReturnShipmentData(
        DhlShipmentData $originalShipmentData
    ): DhlShipmentData {
        $origSender = $originalShipmentData->getSender();
        $origRecipient = $originalShipmentData->getRecipient();
        $origSettings = $originalShipmentData->getShipmentSettings();
        $origServices = $originalShipmentData->getSpecialServices();

        // Customer becomes Sender in return shipment
        $returnSender = new SenderData(
            payer: 'RECIPIENT',
            sapNumber: $origSender->getSapNumber(),
            name: $origRecipient->getName(),
            firstName: $origRecipient->getFirstName(),
            lastName: $origRecipient->getLastName(),
            street: $origRecipient->getStreet(),
            houseNumber: $origRecipient->getHouseNumber(),
            flatNumber: $origRecipient->getFlatNumber(),
            city: $origRecipient->getCity(),
            postalCode: $origRecipient->getPostalCode(),
            country: $origRecipient->getCountry() ?: 'PL',
            phone: $origRecipient->getPhone(),
            email: $origRecipient->getEmail()
        );

        // Merchant/Warehouse becomes Recipient in return shipment
        $returnRecipient = new RecipientData(
            name: $origSender->getName(),
            type: 'B',
            firstName: $origSender->getFirstName(),
            lastName: $origSender->getLastName(),
            contactPerson: $origSender->getName(),
            street: $origSender->getStreet(),
            houseNumber: $origSender->getHouseNumber(),
            flatNumber: $origSender->getFlatNumber(),
            city: $origSender->getCity(),
            postalCode: $origSender->getPostalCode(),
            country: $origSender->getCountry() ?: 'PL',
            phone: $origSender->getPhone(),
            email: $origSender->getEmail()
        );

        $customerCountry = strtoupper($origRecipient->getCountry() ?: 'PL');
        $serviceType = ($customerCountry === 'PL') ? 'ZK' : 'ZC';

        $returnSettings = new ShipmentSettingsData(
            serviceType: $serviceType,
            pickupType: 'COURIER',
            content: $origSettings->getContent(),
            mpk: $origSettings->getMpk(),
            comment: $origSettings->getComment(),
            reference: $origSettings->getReference(),
            labelType: $origSettings->getReturnLabelType() ?: 'BLP',
            returnLabelType: $origSettings->getReturnLabelType(),
            returnQrCodeType: $origSettings->getReturnQrCodeType(),
            returnLabelExpDays: $origSettings->getReturnLabelExpDays(),
            returnBookCourier: $origSettings->getReturnBookCourier(),
            packages: $origSettings->getPackages()
        );

        $returnServices = new SpecialServicesData(
            insurance: $origServices->isInsurance(),
            insuranceValue: $origServices->getInsuranceValue(),
            nonStandard: $origServices->isNonStandard()
        );

        return new DhlShipmentData(
            orderId: $originalShipmentData->getOrderId(),
            sender: $returnSender,
            recipient: $returnRecipient,
            specialServices: $returnServices,
            shipmentSettings: $returnSettings
        );
    }
}
