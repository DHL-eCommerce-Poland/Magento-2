<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;

class ShipmentDataValidator
{
    /**
     * Validate DhlShipmentData object and throw LocalizedException on validation failure.
     *
     * @param DhlShipmentData $shipmentData
     *
     * @return void
     * @throws LocalizedException
     */
    public function validate(DhlShipmentData $shipmentData): void
    {
        $errors = [];

        // 1. Sender validation
        $sender = $shipmentData->getSender();
        if (empty(trim($sender->getName()))) {
            $errors[] = __('Sender Name is required.')->render();
        }
        if (empty(trim($sender->getStreet()))) {
            $errors[] = __('Sender Street is required.')->render();
        }
        if (empty(trim($sender->getHouseNumber()))) {
            $errors[] = __('Sender House Number is required.')->render();
        }
        if (empty(trim($sender->getPostalCode()))) {
            $errors[] = __('Sender Postal Code is required.')->render();
        }
        if (empty(trim($sender->getCity()))) {
            $errors[] = __('Sender City is required.')->render();
        }

        $recipient = $shipmentData->getRecipient();
        $hasRecName = !empty(trim($recipient->getFirstName()))
            || !empty(trim($recipient->getLastName()))
            || !empty(trim($recipient->getName()));
        if (!$hasRecName) {
            $errors[] = __('Recipient Name or First & Last Name is required.')->render();
        }
        if (empty(trim($recipient->getStreet()))) {
            $errors[] = __('Recipient Street is required.')->render();
        }
        if (empty(trim($recipient->getHouseNumber()))) {
            $errors[] = __('Recipient House Number is required.')->render();
        }
        if (empty(trim($recipient->getPostalCode()))) {
            $errors[] = __('Recipient Postal Code is required.')->render();
        }
        if (empty(trim($recipient->getCity()))) {
            $errors[] = __('Recipient City is required.')->render();
        }

        // 3. Settings & Packages validation
        $settings = $shipmentData->getShipmentSettings();
        if (empty(trim($settings->getContent()))) {
            $errors[] = __('Package Contents Description is required.')->render();
        }

        $packages = $settings->getPackages();
        if (empty($packages)) {
            $errors[] = __('At least one package is required.')->render();
        } else {
            foreach ($packages as $index => $pkg) {
                $rowNum = $index + 1;
                if ($pkg->getWeight() <= 0) {
                    $errors[] = __('Package #%1 weight must be greater than zero.', $rowNum)->render();
                }
                if ($pkg->getPackageType() !== 'ENVELOPE') {
                    if ($pkg->getWidth() !== null && $pkg->getWidth() <= 0) {
                        $errors[] = __('Package #%1 width must be greater than zero.', $rowNum)->render();
                    }
                    if ($pkg->getLength() !== null && $pkg->getLength() <= 0) {
                        $errors[] = __('Package #%1 length must be greater than zero.', $rowNum)->render();
                    }
                    if ($pkg->getHeight() !== null && $pkg->getHeight() <= 0) {
                        $errors[] = __('Package #%1 height must be greater than zero.', $rowNum)->render();
                    }
                }
            }
        }

        // 4. Special services validation
        $specialServices = $shipmentData->getSpecialServices();
        if ($specialServices->isCod()) {
            if ($specialServices->getCodAmount() === null || $specialServices->getCodAmount() <= 0) {
                $errors[] = __('COD Amount must be greater than zero when Cash On Delivery is selected.')->render();
            }
        }

        if (!empty($errors)) {
            throw new LocalizedException(__(implode(" ", $errors)));
        }
    }
}
