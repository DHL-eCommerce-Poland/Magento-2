<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api\Data;

interface DeliveryServiceInterface
{
    public const ENTITY_ID = 'entity_id';
    public const WEBSITE_ID = 'website_id';
    public const TEMPLATE_NAME = 'template_name';
    public const SERVICE_CODE = 'service_code';
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
     * Get website ID.
     *
     * @return int
     */
    public function getWebsiteId(): int;

    /**
     * Set website ID.
     *
     * @param int $websiteId
     *
     * @return $this
     */
    public function setWebsiteId(int $websiteId): static;

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
     * Get DHL service code.
     *
     * @return string
     */
    public function getServiceCode(): string;

    /**
     * Set DHL service code.
     *
     * @param string $serviceCode
     *
     * @return $this
     */
    public function setServiceCode(string $serviceCode): static;

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
}
