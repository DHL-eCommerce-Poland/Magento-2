<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Ideo\Dhl\Model\Shipment\Data;

class SpecialServicesData
{
    /**
     * @param string|null $deliveryHours
     * @param bool $saturdayDelivery
     * @param bool $saturdayDispatch
     * @param bool $insurance
     * @param float|null $insuranceValue
     * @param string $insuranceCurrency
     * @param bool $cod
     * @param float|null $codAmount
     * @param string $codCurrency
     * @param string $codPaymentMethod
     * @param bool $pdi
     * @param bool $rod
     * @param string $rodDocumentText
     * @param bool $pod
     * @param bool $euroReturn
     * @param bool $nonStandard
     * @param bool $agever
     * @param bool $odb
     */
    public function __construct(
        private ?string $deliveryHours = null,
        private bool $saturdayDelivery = false,
        private bool $saturdayDispatch = false,
        private bool $insurance = false,
        private ?float $insuranceValue = null,
        private string $insuranceCurrency = 'PLN',
        private bool $cod = false,
        private ?float $codAmount = null,
        private string $codCurrency = 'PLN',
        private string $codPaymentMethod = 'CASH',
        private bool $pdi = false,
        private bool $rod = false,
        private string $rodDocumentText = '',
        private bool $pod = false,
        private bool $euroReturn = false,
        private bool $nonStandard = false,
        private bool $agever = false,
        private bool $odb = false
    ) {
    }

    /**
     * Get preferred delivery hours service code (e.g. 1722 for evening delivery).
     *
     * @return string|null
     */
    public function getDeliveryHours(): ?string
    {
        return $this->deliveryHours;
    }

    /**
     * Check if Saturday delivery service is enabled.
     *
     * @return bool
     */
    public function isSaturdayDelivery(): bool
    {
        return $this->saturdayDelivery;
    }

    /**
     * Check if Saturday pickup/dispatch service is enabled.
     *
     * @return bool
     */
    public function isSaturdayDispatch(): bool
    {
        return $this->saturdayDispatch;
    }

    /**
     * Check if shipment insurance service is enabled.
     *
     * @return bool
     */
    public function isInsurance(): bool
    {
        return $this->insurance;
    }

    /**
     * Get declared insurance value.
     *
     * @return float|null
     */
    public function getInsuranceValue(): ?float
    {
        return $this->insuranceValue;
    }

    /**
     * Get insurance currency code (e.g. PLN).
     *
     * @return string
     */
    public function getInsuranceCurrency(): string
    {
        return $this->insuranceCurrency;
    }

    /**
     * Check if Cash On Delivery (COD) service is enabled.
     *
     * @return bool
     */
    public function isCod(): bool
    {
        return $this->cod;
    }

    /**
     * Get Cash On Delivery (COD) amount.
     *
     * @return float|null
     */
    public function getCodAmount(): ?float
    {
        return $this->codAmount;
    }

    /**
     * Get COD currency code (e.g. PLN).
     *
     * @return string
     */
    public function getCodCurrency(): string
    {
        return $this->codCurrency;
    }

    /**
     * Get COD payment method (e.g. CASH).
     *
     * @return string
     */
    public function getCodPaymentMethod(): string
    {
        return $this->codPaymentMethod;
    }

    /**
     * Check if Pre-delivery Information (PDI) service is enabled.
     *
     * @return bool
     */
    public function isPdi(): bool
    {
        return $this->pdi;
    }

    /**
     * Check if Return Confirmed Documents (ROD) service is enabled.
     *
     * @return bool
     */
    public function isRod(): bool
    {
        return $this->rod;
    }

    /**
     * Get description or reference text for ROD documents.
     *
     * @return string
     */
    public function getRodDocumentText(): string
    {
        return $this->rodDocumentText;
    }

    /**
     * Check if Proof of Delivery (POD) service is enabled.
     *
     * @return bool
     */
    public function isPod(): bool
    {
        return $this->pod;
    }

    /**
     * Check if Euro pallet return service is enabled.
     *
     * @return bool
     */
    public function isEuroReturn(): bool
    {
        return $this->euroReturn;
    }

    /**
     * Check if Non-Standard (NST) package option is enabled.
     *
     * @return bool
     */
    public function isNonStandard(): bool
    {
        return $this->nonStandard;
    }

    /**
     * Check if Adult Verification (AGEVER) service is enabled.
     *
     * @return bool
     */
    public function isAgever(): bool
    {
        return $this->agever;
    }

    /**
     * Check if Self Pickup (ODB) service is enabled.
     *
     * @return bool
     */
    public function isOdb(): bool
    {
        return $this->odb;
    }

    /**
     * Convert special services object to array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'deliveryHours' => $this->deliveryHours,
            'saturdayDelivery' => $this->saturdayDelivery,
            'saturdayDispatch' => $this->saturdayDispatch,
            'insurance' => $this->insurance,
            'insuranceValue' => $this->insurance ? $this->insuranceValue : null,
            'insuranceCurrency' => $this->insuranceCurrency,
            'cod' => $this->cod,
            'codAmount' => $this->cod ? $this->codAmount : null,
            'codCurrency' => $this->codCurrency,
            'codPaymentMethod' => $this->codPaymentMethod,
            'pdi' => $this->pdi,
            'rod' => $this->rod,
            'rodDocumentText' => $this->rod ? $this->rodDocumentText : '',
            'pod' => $this->pod,
            'euroReturn' => $this->euroReturn,
            'nonStandard' => $this->nonStandard,
            'agever' => $this->agever,
            'odb' => $this->odb,
        ];
    }

    /**
     * Create SpecialServicesData object from array representation.
     *
     * @param array $data
     *
     * @return self
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function fromArray(array $data): self
    {
        $insurance = filter_var($data['insurance'] ?? $data['service_UBEZP'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $insuranceValRaw = $data['insuranceValue'] ?? $data['serviceValueUbez'] ?? null;
        $insuranceValue = ($insurance && $insuranceValRaw !== null && $insuranceValRaw !== '')
            ? (float)$insuranceValRaw
            : null;

        $cod = filter_var($data['cod'] ?? $data['service_COD'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $codAmtRaw = $data['codAmount'] ?? $data['serviceValueCOD'] ?? null;
        $codAmount = ($cod && $codAmtRaw !== null && $codAmtRaw !== '') ? (float)$codAmtRaw : null;

        $rod = filter_var($data['rod'] ?? $data['returnOnDelivery'] ?? $data['service_ROD'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $rodDocRaw = (string)($data['rodDocumentText'] ?? $data['textInstruction'] ?? '');
        $rodDocumentText = $rod ? trim($rodDocRaw) : '';

        return new self(
            $data['deliveryHours'] ?? null,
            filter_var($data['saturdayDelivery'] ?? $data['service_SOBOTA'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($data['saturdayDispatch'] ?? $data['pickupOnSaturday'] ?? $data['service_NAD_SOBOTA'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $insurance,
            $insuranceValue,
            (string)($data['insuranceCurrency'] ?? 'PLN'),
            $cod,
            $codAmount,
            (string)($data['codCurrency'] ?? 'PLN'),
            (string)($data['codPaymentMethod'] ?? 'CASH'),
            filter_var($data['pdi'] ?? $data['predeliveryInformation'] ?? $data['service_PDI'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $rod,
            $rodDocumentText,
            filter_var($data['pod'] ?? $data['proofOfDelivery'] ?? $data['service_POD'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($data['euroReturn'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($data['nonStandard'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($data['agever'] ?? $data['service_AGEVER'] ?? false, FILTER_VALIDATE_BOOLEAN),
            filter_var($data['odb'] ?? $data['service_ODB'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
    }
}
