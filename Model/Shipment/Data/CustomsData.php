<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment\Data;

class CustomsData
{
    /**
     * @param int|null $selectedCustomsTemplateId
     * @param string $customsType
     * @param string $categoryOfItem
     * @param string $currency
     * @param string $countryOfOrigin
     * @param string|null $defaultTariffCode
     * @param float|null $costsOfShipment
     * @param float|null $grossWeight
     * @param string|null $firstName
     * @param string|null $secondaryName
     * @param string|null $nipNr
     * @param string|null $eoriNr
     * @param string|null $vatRegistrationNumber
     * @param string|null $eoriNrReceiver
     * @param string|null $vatRegistrationNumberReceiver
     * @param string|null $invoiceNr
     * @param string|null $invoiceDate
     * @param string|null $invoiceFile
     * @param string|null $additionalInfo
     * @param bool $notExceedValue
     * @param bool $notProhibitedGoods
     * @param bool $notRestrictedGoods
     * @param bool $invoiceContent
     * @param array $customsItems
     */
    public function __construct(
        private readonly ?int $selectedCustomsTemplateId = null,
        private readonly string $customsType = 'U',
        private readonly string $categoryOfItem = '11',
        private readonly string $currency = 'EUR',
        private readonly string $countryOfOrigin = 'PL',
        private readonly ?string $defaultTariffCode = null,
        private readonly ?float $costsOfShipment = null,
        private readonly ?float $grossWeight = null,
        private readonly ?string $firstName = null,
        private readonly ?string $secondaryName = null,
        private readonly ?string $nipNr = null,
        private readonly ?string $eoriNr = null,
        private readonly ?string $vatRegistrationNumber = null,
        private readonly ?string $eoriNrReceiver = null,
        private readonly ?string $vatRegistrationNumberReceiver = null,
        private readonly ?string $invoiceNr = null,
        private readonly ?string $invoiceDate = null,
        private readonly ?string $invoiceFile = null,
        private readonly ?string $additionalInfo = null,
        private readonly bool $notExceedValue = true,
        private readonly bool $notProhibitedGoods = true,
        private readonly bool $notRestrictedGoods = true,
        private readonly bool $invoiceContent = false,
        private readonly array $customsItems = []
    ) {
    }

    /**
     * Get selected customs template ID.
     *
     * @return int|null
     */
    public function getSelectedCustomsTemplateId(): ?int
    {
        return $this->selectedCustomsTemplateId;
    }

    /**
     * Get customs clearance type.
     *
     * @return string
     */
    public function getCustomsType(): string
    {
        return $this->customsType;
    }

    /**
     * Get category of item.
     *
     * @return string
     */
    public function getCategoryOfItem(): string
    {
        return $this->categoryOfItem;
    }

    /**
     * Get currency.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get country of origin ISO code.
     *
     * @return string
     */
    public function getCountryOfOrigin(): string
    {
        return $this->countryOfOrigin;
    }

    /**
     * Get default HS tariff code.
     *
     * @return string|null
     */
    public function getDefaultTariffCode(): ?string
    {
        return $this->defaultTariffCode;
    }

    /**
     * Get costs of shipment.
     *
     * @return float|null
     */
    public function getCostsOfShipment(): ?float
    {
        return $this->costsOfShipment;
    }

    /**
     * Get gross weight in kg.
     *
     * @return float|null
     */
    public function getGrossWeight(): ?float
    {
        return $this->grossWeight;
    }

    /**
     * Get contact person first name.
     *
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * Get contact person last name.
     *
     * @return string|null
     */
    public function getSecondaryName(): ?string
    {
        return $this->secondaryName;
    }

    /**
     * Get shipper NIP.
     *
     * @return string|null
     */
    public function getNipNr(): ?string
    {
        return $this->nipNr;
    }

    /**
     * Get shipper EORI.
     *
     * @return string|null
     */
    public function getEoriNr(): ?string
    {
        return $this->eoriNr;
    }

    /**
     * Get shipper VAT registration number.
     *
     * @return string|null
     */
    public function getVatRegistrationNumber(): ?string
    {
        return $this->vatRegistrationNumber;
    }

    /**
     * Get receiver EORI number.
     *
     * @return string|null
     */
    public function getEoriNrReceiver(): ?string
    {
        return $this->eoriNrReceiver;
    }

    /**
     * Get receiver VAT registration number.
     *
     * @return string|null
     */
    public function getVatRegistrationNumberReceiver(): ?string
    {
        return $this->vatRegistrationNumberReceiver;
    }

    /**
     * Get invoice number.
     *
     * @return string|null
     */
    public function getInvoiceNr(): ?string
    {
        return $this->invoiceNr;
    }

    /**
     * Get invoice date.
     *
     * @return string|null
     */
    public function getInvoiceDate(): ?string
    {
        return $this->invoiceDate;
    }

    /**
     * Get invoice PDF file path.
     *
     * @return string|null
     */
    public function getInvoiceFile(): ?string
    {
        return $this->invoiceFile;
    }

    /**
     * Get additional customs information.
     *
     * @return string|null
     */
    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    /**
     * Get not exceed value declaration.
     *
     * @return bool
     */
    public function isNotExceedValue(): bool
    {
        return $this->notExceedValue;
    }

    /**
     * Get not prohibited goods declaration.
     *
     * @return bool
     */
    public function isNotProhibitedGoods(): bool
    {
        return $this->notProhibitedGoods;
    }

    /**
     * Get not restricted goods declaration.
     *
     * @return bool
     */
    public function isNotRestrictedGoods(): bool
    {
        return $this->notRestrictedGoods;
    }

    /**
     * Get invoice content declaration.
     *
     * @return bool
     */
    public function isInvoiceContent(): bool
    {
        return $this->invoiceContent;
    }

    /**
     * Get customs item list.
     *
     * @return array
     */
    public function getCustomsItems(): array
    {
        return $this->customsItems;
    }

    /**
     * Convert DTO to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'selectedCustomsTemplateId' => $this->selectedCustomsTemplateId,
            'customsType' => $this->customsType,
            'categoryOfItem' => $this->categoryOfItem,
            'currency' => $this->currency,
            'countryOfOrigin' => $this->countryOfOrigin,
            'defaultTariffCode' => $this->defaultTariffCode,
            'costsOfShipment' => $this->costsOfShipment,
            'grossWeight' => $this->grossWeight,
            'firstName' => $this->firstName,
            'secondaryName' => $this->secondaryName,
            'nipNr' => $this->nipNr,
            'eoriNr' => $this->eoriNr,
            'vatRegistrationNumber' => $this->vatRegistrationNumber,
            'eoriNrReceiver' => $this->eoriNrReceiver,
            'vatRegistrationNumberReceiver' => $this->vatRegistrationNumberReceiver,
            'invoiceNr' => $this->invoiceNr,
            'invoiceDate' => $this->invoiceDate,
            'invoiceFile' => $this->invoiceFile,
            'additionalInfo' => $this->additionalInfo,
            'notExceedValue' => $this->notExceedValue,
            'notProhibitedGoods' => $this->notProhibitedGoods,
            'notRestrictedGoods' => $this->notRestrictedGoods,
            'invoiceContent' => $this->invoiceContent,
            'customsItems' => $this->customsItems,
        ];
    }

    /**
     * Create DTO from array.
     *
     * @param array $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $templateId = isset($data['selectedCustomsTemplateId']) && $data['selectedCustomsTemplateId'] !== ''
            ? (int)$data['selectedCustomsTemplateId']
            : null;

        $toFloat = static function (mixed $v): ?float {
            if ($v === null || $v === '') {
                return null;
            }
            if (is_string($v)) {
                $v = str_replace(',', '.', trim($v));
            }
            return (float)$v;
        };

        return new self(
            $templateId,
            (string)($data['customsType'] ?? 'U'),
            (string)($data['categoryOfItem'] ?? '11'),
            (string)($data['currency'] ?? 'EUR'),
            (string)($data['countryOfOrigin'] ?? 'PL'),
            isset($data['defaultTariffCode']) ? (string)$data['defaultTariffCode'] : null,
            isset($data['costsOfShipment']) && $data['costsOfShipment'] !== ''
                ? $toFloat($data['costsOfShipment']) : null,
            isset($data['grossWeight']) && $data['grossWeight'] !== '' ? $toFloat($data['grossWeight']) : null,
            isset($data['firstName']) ? (string)$data['firstName'] : null,
            isset($data['secondaryName']) ? (string)$data['secondaryName'] : null,
            isset($data['nipNr'])
                ? (string)$data['nipNr']
                : (isset($data['shipperNip']) ? (string)$data['shipperNip'] : null),
            isset($data['eoriNr'])
                ? (string)$data['eoriNr']
                : (isset($data['shipperEori']) ? (string)$data['shipperEori'] : null),
            isset($data['vatRegistrationNumber'])
                ? (string)$data['vatRegistrationNumber']
                : (isset($data['shipperVatNo']) ? (string)$data['shipperVatNo'] : null),
            isset($data['eoriNrReceiver'])
                ? (string)$data['eoriNrReceiver']
                : (isset($data['receiverEori']) ? (string)$data['receiverEori'] : null),
            isset($data['vatRegistrationNumberReceiver'])
                ? (string)$data['vatRegistrationNumberReceiver']
                : (isset($data['receiverVatNo']) ? (string)$data['receiverVatNo'] : null),
            isset($data['invoiceNr']) ? (string)$data['invoiceNr'] : null,
            isset($data['invoiceDate']) ? (string)$data['invoiceDate'] : null,
            isset($data['invoiceFile']) ? (string)$data['invoiceFile'] : null,
            isset($data['additionalInfo']) ? (string)$data['additionalInfo'] : null,
            isset($data['notExceedValue']) ? (bool)$data['notExceedValue'] : true,
            isset($data['notProhibitedGoods']) ? (bool)$data['notProhibitedGoods'] : true,
            isset($data['notRestrictedGoods']) ? (bool)$data['notRestrictedGoods'] : true,
            isset($data['invoiceContent']) ? (bool)$data['invoiceContent'] : false,
            is_array($data['customsItems'] ?? null) ? $data['customsItems'] : []
        );
    }
}
