<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\MethodCatalog;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class ShipmentDataValidator
{
    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

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

        // 2. Recipient validation
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
        $serviceType = $settings->getServiceType();
        $popId = $recipient->getPopId();

        if (empty(trim($settings->getContent()))) {
            $errors[] = __('Package Contents Description is required.')->render();
        }

        // ServicePoint / POP point requirement
        if (in_array(strtoupper(trim((string)$serviceType)), [MethodCatalog::SERVICE_SP, MethodCatalog::SERVICE_PS], true) && empty($popId)) {
            $errors[] = __('Service Point ID is required for ServicePoint shipments.')->render();
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

        // 3.1 Shipment date validation
        $shipmentDate = trim((string)$settings->getShipmentDate());
        if (!empty($shipmentDate)) {
            $today = $this->timezone->scopeDate()->format('Y-m-d');
            if ($shipmentDate < $today) {
                $errors[] = __('Shipment date cannot be in the past.')->render();
            }
        }

        // 4. Pickup hours validation
        $pFrom = $settings->getPickupHourFrom();
        $pTo = $settings->getPickupHourTo();
        if (($pFrom !== null && trim($pFrom) !== '') || ($pTo !== null && trim($pTo) !== '')) {
            if (empty($pFrom) || empty($pTo)) {
                $errors[] = __('Both Courier Pickup Time [From] and [To] must be specified.')->render();
            } else {
                $tsFrom = strtotime('1970-01-01 ' . $pFrom);
                $tsTo = strtotime('1970-01-01 ' . $pTo);
                if ($tsFrom !== false && $tsTo !== false && $tsFrom >= $tsTo) {
                    $errors[] = __('Courier Pickup Time [From] must be earlier than Pickup Time [To].')->render();
                }
            }
        }

        // 5. Special services validation
        $specialServices = $shipmentData->getSpecialServices();
        if ($specialServices->isCod()) {
            if ($specialServices->getCodAmount() === null || $specialServices->getCodAmount() <= 0) {
                $errors[] = __('COD Amount must be greater than zero when Cash On Delivery is selected.')->render();
            }
        }
        if ($specialServices->isInsurance()) {
            if ($specialServices->getInsuranceValue() === null || $specialServices->getInsuranceValue() <= 0) {
                $errors[] = __('Insurance Value must be greater than zero when Insurance is selected.')->render();
            }
        }
        if ($specialServices->isRod()) {
            if (empty(trim((string)$specialServices->getRodDocumentText()))) {
                $errors[] = __('ROD Document Name is required when ROD is selected.')->render();
            }
        }

        if (!empty($errors)) {
            throw new LocalizedException(__(implode("\n", array_unique($errors))));
        }
    }

    /**
     * Validate raw request payload for numeric fields containing commas.
     *
     * @param array $payload
     *
     * @return void
     * @throws LocalizedException
     */
    public function validateRawPayload(array $payload): void
    {
        $errors = [];

        // 1. Packages
        $packages = $payload['shipmentSettings']['packages'] ?? ($payload['packages'] ?? []);
        if (is_array($packages)) {
            foreach ($packages as $index => $pkg) {
                if (!is_array($pkg)) {
                    continue;
                }
                $rowNum = $index + 1;
                $numericFields = [
                    'weight' => __('Package #%1 weight', $rowNum),
                    'width' => __('Package #%1 width', $rowNum),
                    'length' => __('Package #%1 length', $rowNum),
                    'height' => __('Package #%1 height', $rowNum),
                    'quantity' => __('Package #%1 quantity', $rowNum),
                ];

                foreach ($numericFields as $fieldKey => $label) {
                    if (isset($pkg[$fieldKey]) && is_string($pkg[$fieldKey]) && str_contains($pkg[$fieldKey], ',')) {
                        $errors[] = __('Field "%1" cannot contain a comma. Please use a dot (.) as decimal separator.', $label)->render();
                    }
                }
            }
        }

        // 2. Special Services
        $ss = $payload['specialServices'] ?? [];
        if (is_array($ss)) {
            $ssFields = [
                'insuranceValue' => __('Insurance Value'),
                'codAmount' => __('COD Amount'),
            ];
            foreach ($ssFields as $fieldKey => $label) {
                if (isset($ss[$fieldKey]) && is_string($ss[$fieldKey]) && str_contains($ss[$fieldKey], ',')) {
                    $errors[] = __('Field "%1" cannot contain a comma. Please use a dot (.) as decimal separator.', $label)->render();
                }
            }
        }

        // 3. Shipment Settings
        $st = $payload['shipmentSettings'] ?? [];
        if (is_array($st)) {
            if (isset($st['returnLabelExpDays']) && is_string($st['returnLabelExpDays']) && str_contains($st['returnLabelExpDays'], ',')) {
                $errors[] = __('Field "%1" cannot contain a comma. Please use a dot (.) as decimal separator.', __('Return Label Expiration Days'))->render();
            }
        }

        // 4. Customs Clearance
        $customs = $payload['customs'] ?? [];
        if (is_array($customs)) {
            $customsFields = [
                'costsOfShipment' => __('Gross Costs of Shipment'),
                'grossWeight' => __('Gross Weight'),
            ];
            foreach ($customsFields as $fieldKey => $label) {
                if (isset($customs[$fieldKey]) && is_string($customs[$fieldKey]) && str_contains($customs[$fieldKey], ',')) {
                    $errors[] = __('Field "%1" cannot contain a comma. Please use a dot (.) as decimal separator.', $label)->render();
                }
            }

            $items = $customs['customsItems'] ?? [];
            if (is_array($items)) {
                foreach ($items as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $rowNum = $index + 1;
                    $itemFields = [
                        'weight' => __('Customs Item #%1 weight', $rowNum),
                        'value' => __('Customs Item #%1 value', $rowNum),
                        'quantity' => __('Customs Item #%1 quantity', $rowNum),
                    ];
                    foreach ($itemFields as $fieldKey => $label) {
                        if (isset($item[$fieldKey]) && is_string($item[$fieldKey]) && str_contains($item[$fieldKey], ',')) {
                            $errors[] = __('Field "%1" cannot contain a comma. Please use a dot (.) as decimal separator.', $label)->render();
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new LocalizedException(__(implode("\n", array_unique($errors))));
        }
    }
}

