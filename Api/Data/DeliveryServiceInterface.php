<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api\Data;

interface DeliveryServiceInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TEMPLATE_NAME = 'template_name';
    public const METHOD_CODE = 'method_code';
    public const PACKAGE_TYPE = 'package_type';
    public const WEIGHT = 'weight';
    public const LENGTH = 'length';
    public const WIDTH = 'width';
    public const HEIGHT = 'height';
    public const IS_NON_STANDARD = 'is_non_standard';
    public const HAS_EURO_RETURN = 'has_euro_return';
    public const CONTENT = 'content';
    public const COMMENT = 'comment';
    public const REFERENCE = 'reference';
    public const MPK = 'mpk';
    public const ADDITIONAL_SERVICES = 'additional_services';
    public const LABEL_TYPE = 'label_type';
    public const DROP_OFF_POINT_ID = 'drop_off_point_id';
    public const ADDRESS_TYPE = 'address_type';
    public const AUTO_CREATE_RETURN_SHIPMENT = 'auto_create_return_shipment';
    public const RETURN_LABEL_TYPE = 'return_label_type';
    public const RETURN_QR_CODE_TYPE = 'return_qr_code_type';
    public const RETURN_LABEL_EXP_DAYS = 'return_label_exp_days';
    public const RETURN_BOOK_COURIER = 'return_book_courier';

    /**
     * Get entity ID.
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Set entity ID.
     *
     * @param mixed $entityId
     *
     * @return $this
     */
    public function setEntityId($entityId): static;

    /**
     * Get template name.
     *
     * @return string
     */
    public function getTemplateName(): string;

    /**
     * Set template name.
     *
     * @param string $templateName
     *
     * @return $this
     */
    public function setTemplateName(string $templateName): static;

    /**
     * Get shipping method code.
     *
     * @return string|null
     */
    public function getMethodCode(): ?string;

    /**
     * Set shipping method code.
     *
     * @param string|null $methodCode
     *
     * @return $this
     */
    public function setMethodCode(?string $methodCode): static;

    /**
     * Get package type.
     *
     * @return string
     */
    public function getPackageType(): string;

    /**
     * Set package type.
     *
     * @param string $packageType
     *
     * @return $this
     */
    public function setPackageType(string $packageType): static;

    /**
     * Get weight in KG.
     *
     * @return float
     */
    public function getWeight(): float;

    /**
     * Set weight in KG.
     *
     * @param float $weight
     *
     * @return $this
     */
    public function setWeight(float $weight): static;

    /**
     * Get length in CM.
     *
     * @return float
     */
    public function getLength(): float;

    /**
     * Set length in CM.
     *
     * @param float $length
     *
     * @return $this
     */
    public function setLength(float $length): static;

    /**
     * Get width in CM.
     *
     * @return float
     */
    public function getWidth(): float;

    /**
     * Set width in CM.
     *
     * @param float $width
     *
     * @return $this
     */
    public function setWidth(float $width): static;

    /**
     * Get height in CM.
     *
     * @return float
     */
    public function getHeight(): float;

    /**
     * Set height in CM.
     *
     * @param float $height
     *
     * @return $this
     */
    public function setHeight(float $height): static;

    /**
     * Get is non standard package flag.
     *
     * @return bool
     */
    public function getIsNonStandard(): bool;

    /**
     * Set is non standard package flag.
     *
     * @param bool $isNonStandard
     *
     * @return $this
     */
    public function setIsNonStandard(bool $isNonStandard): static;

    /**
     * Get has euro return flag.
     *
     * @return bool
     */
    public function getHasEuroReturn(): bool;

    /**
     * Set has euro return flag.
     *
     * @param bool $hasEuroReturn
     *
     * @return $this
     */
    public function setHasEuroReturn(bool $hasEuroReturn): static;

    /**
     * Get package contents description.
     *
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * Set package contents description.
     *
     * @param string|null $content
     *
     * @return $this
     */
    public function setContent(?string $content): static;

    /**
     * Get special comments.
     *
     * @return string|null
     */
    public function getComment(): ?string;

    /**
     * Set special comments.
     *
     * @param string|null $comment
     *
     * @return $this
     */
    public function setComment(?string $comment): static;

    /**
     * Get reference number.
     *
     * @return string|null
     */
    public function getReference(): ?string;

    /**
     * Set reference number.
     *
     * @param string|null $reference
     *
     * @return $this
     */
    public function setReference(?string $reference): static;

    /**
     * Get MPK cost center code.
     *
     * @return string|null
     */
    public function getMpk(): ?string;

    /**
     * Set MPK cost center code.
     *
     * @param string|null $mpk
     *
     * @return $this
     */
    public function setMpk(?string $mpk): static;

    /**
     * Get additional services array.
     *
     * @return array|null
     */
    public function getAdditionalServices(): ?array;

    /**
     * Set additional services array.
     *
     * @param array|null $additionalServices
     *
     * @return $this
     */
    public function setAdditionalServices(?array $additionalServices): static;

    /**
     * Get label format type.
     *
     * @return string
     */
    public function getLabelType(): string;

    /**
     * Set label format type.
     *
     * @param string $labelType
     *
     * @return $this
     */
    public function setLabelType(string $labelType): static;

    /**
     * Get default drop off point ID.
     *
     * @return int|null
     */
    public function getDropOffPointId(): ?int;

    /**
     * Set default drop off point ID.
     *
     * @param int|null $dropOffPointId
     *
     * @return $this
     */
    public function setDropOffPointId(?int $dropOffPointId): static;

    /**
     * Get address type.
     *
     * @return string|null
     */
    public function getAddressType(): ?string;

    /**
     * Set address type.
     *
     * @param string|null $addressType
     *
     * @return $this
     */
    public function setAddressType(?string $addressType): static;

    /**
     * Get auto create return shipment flag.
     *
     * @return bool
     */
    public function getAutoCreateReturnShipment(): bool;

    /**
     * Set auto create return shipment flag.
     *
     * @param bool $autoCreateReturnShipment
     *
     * @return $this
     */
    public function setAutoCreateReturnShipment(bool $autoCreateReturnShipment): static;

    /**
     * Get return label type.
     *
     * @return string|null
     */
    public function getReturnLabelType(): ?string;

    /**
     * Set return label type.
     *
     * @param string|null $returnLabelType
     *
     * @return $this
     */
    public function setReturnLabelType(?string $returnLabelType): static;

    /**
     * Get return QR code type.
     *
     * @return string|null
     */
    public function getReturnQrCodeType(): ?string;

    /**
     * Set return QR code type.
     *
     * @param string|null $returnQrCodeType
     *
     * @return $this
     */
    public function setReturnQrCodeType(?string $returnQrCodeType): static;

    /**
     * Get return label expiration days.
     *
     * @return int|null
     */
    public function getReturnLabelExpDays(): ?int;

    /**
     * Set return label expiration days.
     *
     * @param int|null $returnLabelExpDays
     *
     * @return $this
     */
    public function setReturnLabelExpDays(?int $returnLabelExpDays): static;

    /**
     * Get return book courier method.
     *
     * @return int|null
     */
    public function getReturnBookCourier(): ?int;

    /**
     * Set return book courier method.
     *
     * @param int|null $returnBookCourier
     *
     * @return $this
     */
    public function setReturnBookCourier(?int $returnBookCourier): static;
}
