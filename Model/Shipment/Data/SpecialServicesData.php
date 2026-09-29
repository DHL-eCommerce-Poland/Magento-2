<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Ideo\Dhl\Model\Shipment\Data;

class SpecialServicesData
{
    public const PAYMENT_METHOD_BANK_TRANSFER = 'BANK_TRANSFER';
    public const DELIVERY_HOURS_EVENING = '1722';

    /**
     * @param string|null $deliveryHours
     * @param bool $deliveryEvening
     * @param bool $deliveryOnSaturday
     * @param bool $pickupOnSaturday
     * @param bool $insurance
     * @param float|null $insuranceValue
     * @param bool $collectOnDelivery
     * @param float|null $collectOnDeliveryValue
     * @param string $collectOnDeliveryForm
     * @param bool $predeliveryInformation
     * @param bool $returnOnDelivery
     * @param string $returnOnDeliveryReference
     * @param bool $proofOfDelivery
     * @param bool $euroReturn
     * @param bool $nonStandard
     * @param bool $ageVer
     * @param bool $selfCollect
     */
    public function __construct(
        private ?string $deliveryHours = null,
        private bool $deliveryEvening = false,
        private bool $deliveryOnSaturday = false,
        private bool $pickupOnSaturday = false,
        private bool $insurance = false,
        private ?float $insuranceValue = null,
        private bool $collectOnDelivery = false,
        private ?float $collectOnDeliveryValue = null,
        private string $collectOnDeliveryForm = self::PAYMENT_METHOD_BANK_TRANSFER,
        private bool $predeliveryInformation = false,
        private bool $returnOnDelivery = false,
        private string $returnOnDeliveryReference = '',
        private bool $proofOfDelivery = false,
        private bool $euroReturn = false,
        private bool $nonStandard = false,
        private bool $ageVer = false,
        private bool $selfCollect = false
    ) {
        if ($this->deliveryEvening && $this->deliveryHours === null) {
            $this->deliveryHours = self::DELIVERY_HOURS_EVENING;
        } elseif ($this->deliveryHours === self::DELIVERY_HOURS_EVENING) {
            $this->deliveryEvening = true;
        }
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
     * Check if Evening Delivery service is enabled.
     *
     * @return bool
     */
    public function isDeliveryEvening(): bool
    {
        return $this->deliveryEvening || $this->deliveryHours === self::DELIVERY_HOURS_EVENING;
    }

    /**
     * Check if Saturday delivery service is enabled.
     *
     * @return bool
     */
    public function isDeliveryOnSaturday(): bool
    {
        return $this->deliveryOnSaturday;
    }

    /**
     * Check if Saturday pickup/dispatch service is enabled.
     *
     * @return bool
     */
    public function isPickupOnSaturday(): bool
    {
        return $this->pickupOnSaturday;
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
     * Check if Cash On Delivery (COD) service is enabled.
     *
     * @return bool
     */
    public function isCollectOnDelivery(): bool
    {
        return $this->collectOnDelivery;
    }

    /**
     * Get Cash On Delivery (COD) amount.
     *
     * @return float|null
     */
    public function getCollectOnDeliveryValue(): ?float
    {
        return $this->collectOnDeliveryValue;
    }

    /**
     * Get COD payment method (e.g. BANK_TRANSFER).
     *
     * @return string
     */
    public function getCollectOnDeliveryForm(): string
    {
        return $this->collectOnDeliveryForm;
    }

    /**
     * Check if Pre-delivery Information (PDI) service is enabled.
     *
     * @return bool
     */
    public function isPredeliveryInformation(): bool
    {
        return $this->predeliveryInformation;
    }

    /**
     * Check if Return Confirmed Documents (ROD) service is enabled.
     *
     * @return bool
     */
    public function isReturnOnDelivery(): bool
    {
        return $this->returnOnDelivery;
    }

    /**
     * Get description or reference text for ROD documents.
     *
     * @return string
     */
    public function getReturnOnDeliveryReference(): string
    {
        return $this->returnOnDeliveryReference;
    }

    /**
     * Check if Proof of Delivery (POD) service is enabled.
     *
     * @return bool
     */
    public function isProofOfDelivery(): bool
    {
        return $this->proofOfDelivery;
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
     * Check if Adult Verification (AGEVER / ageVer) service is enabled.
     *
     * @return bool
     */
    public function isAgeVer(): bool
    {
        return $this->ageVer;
    }

    /**
     * Check if Self Pickup (ODB / selfCollect) service is enabled.
     *
     * @return bool
     */
    public function isSelfCollect(): bool
    {
        return $this->selfCollect;
    }

    /**
     * Convert special services object to array representation using official DHL WebAPI2 names.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'deliveryHours' => $this->deliveryHours,
            'deliveryEvening' => $this->isDeliveryEvening(),
            'deliveryOnSaturday' => $this->deliveryOnSaturday,
            'pickupOnSaturday' => $this->pickupOnSaturday,
            'insurance' => $this->insurance,
            'insuranceValue' => $this->insurance ? $this->insuranceValue : null,
            'collectOnDelivery' => $this->collectOnDelivery,
            'collectOnDeliveryValue' => $this->collectOnDelivery ? $this->collectOnDeliveryValue : null,
            'collectOnDeliveryForm' => $this->collectOnDeliveryForm,
            'predeliveryInformation' => $this->predeliveryInformation,
            'returnOnDelivery' => $this->returnOnDelivery,
            'returnOnDeliveryReference' => $this->returnOnDelivery ? $this->returnOnDeliveryReference : '',
            'proofOfDelivery' => $this->proofOfDelivery,
            'euroReturn' => $this->euroReturn,
            'nonStandard' => $this->nonStandard,
            'ageVer' => $this->ageVer,
            'selfCollect' => $this->selfCollect,
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
        $insurance = filter_var($data['insurance'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $insuranceValRaw = $data['insuranceValue'] ?? null;
        $insuranceValue = ($insurance && $insuranceValRaw !== null && $insuranceValRaw !== '')
            ? (float)$insuranceValRaw
            : null;

        $cod = filter_var($data['collectOnDelivery'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $codAmtRaw = $data['collectOnDeliveryValue'] ?? null;
        $codAmount = ($cod && $codAmtRaw !== null && $codAmtRaw !== '') ? (float)$codAmtRaw : null;

        $rod = filter_var($data['returnOnDelivery'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $rodDocRaw = (string)($data['returnOnDeliveryReference'] ?? '');
        $rodDocumentText = $rod ? trim($rodDocRaw) : '';

        $isEvening = filter_var($data['deliveryEvening'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || (isset($data['deliveryHours']) && (string)$data['deliveryHours'] === '1722');
        $deliveryHours = (isset($data['deliveryHours']) && $data['deliveryHours'] !== '1722')
            ? (string)$data['deliveryHours']
            : ($isEvening ? '1722' : null);

        return new self(
            deliveryHours: $deliveryHours,
            deliveryEvening: $isEvening,
            deliveryOnSaturday: filter_var($data['deliveryOnSaturday'] ?? false, FILTER_VALIDATE_BOOLEAN),
            pickupOnSaturday: filter_var($data['pickupOnSaturday'] ?? false, FILTER_VALIDATE_BOOLEAN),
            insurance: $insurance,
            insuranceValue: $insuranceValue,
            collectOnDelivery: $cod,
            collectOnDeliveryValue: $codAmount,
            collectOnDeliveryForm: (string)($data['collectOnDeliveryForm'] ?? self::PAYMENT_METHOD_BANK_TRANSFER),
            predeliveryInformation: filter_var($data['predeliveryInformation'] ?? false, FILTER_VALIDATE_BOOLEAN),
            returnOnDelivery: $rod,
            returnOnDeliveryReference: $rodDocumentText,
            proofOfDelivery: filter_var($data['proofOfDelivery'] ?? false, FILTER_VALIDATE_BOOLEAN),
            euroReturn: filter_var($data['euroReturn'] ?? false, FILTER_VALIDATE_BOOLEAN),
            nonStandard: filter_var($data['nonStandard'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ageVer: filter_var($data['ageVer'] ?? false, FILTER_VALIDATE_BOOLEAN),
            selfCollect: filter_var($data['selfCollect'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
    }
}
