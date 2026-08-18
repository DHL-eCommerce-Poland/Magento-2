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
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param mixed $entityId
     *
     * @return $this
     */
    public function setEntityId($entityId): static;

    /**
     * @return int
     */
    public function getWebsiteId(): int;

    /**
     * @param int $websiteId
     *
     * @return $this
     */
    public function setWebsiteId(int $websiteId): static;

    /**
     * @return string
     */
    public function getTemplateName(): string;

    /**
     * @param string $templateName
     *
     * @return $this
     */
    public function setTemplateName(string $templateName): static;

    /**
     * @return string
     */
    public function getServiceCode(): string;

    /**
     * @param string $serviceCode
     *
     * @return $this
     */
    public function setServiceCode(string $serviceCode): static;

    /**
     * @return string|null
     */
    public function getMethodCode(): ?string;

    /**
     * @param string|null $methodCode
     *
     * @return $this
     */
    public function setMethodCode(?string $methodCode): static;

    /**
     * @return string
     */
    public function getPackageType(): string;

    /**
     * @param string $packageType
     *
     * @return $this
     */
    public function setPackageType(string $packageType): static;

    /**
     * @return float
     */
    public function getWeight(): float;

    /**
     * @param float $weight
     *
     * @return $this
     */
    public function setWeight(float $weight): static;

    /**
     * @return float
     */
    public function getLength(): float;

    /**
     * @param float $length
     *
     * @return $this
     */
    public function setLength(float $length): static;

    /**
     * @return float
     */
    public function getWidth(): float;

    /**
     * @param float $width
     *
     * @return $this
     */
    public function setWidth(float $width): static;

    /**
     * @return float
     */
    public function getHeight(): float;

    /**
     * @param float $height
     *
     * @return $this
     */
    public function setHeight(float $height): static;

    /**
     * @return bool
     */
    public function getIsNonStandard(): bool;

    /**
     * @param bool $isNonStandard
     *
     * @return $this
     */
    public function setIsNonStandard(bool $isNonStandard): static;

    /**
     * @return bool
     */
    public function getHasEuroReturn(): bool;

    /**
     * @param bool $hasEuroReturn
     *
     * @return $this
     */
    public function setHasEuroReturn(bool $hasEuroReturn): static;

    /**
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * @param string|null $content
     *
     * @return $this
     */
    public function setContent(?string $content): static;

    /**
     * @return string|null
     */
    public function getComment(): ?string;

    /**
     * @param string|null $comment
     *
     * @return $this
     */
    public function setComment(?string $comment): static;

    /**
     * @return string|null
     */
    public function getReference(): ?string;

    /**
     * @param string|null $reference
     *
     * @return $this
     */
    public function setReference(?string $reference): static;

    /**
     * @return string|null
     */
    public function getMpk(): ?string;

    /**
     * @param string|null $mpk
     *
     * @return $this
     */
    public function setMpk(?string $mpk): static;

    /**
     * @return array|null
     */
    public function getAdditionalServices(): ?array;

    /**
     * @param array|null $additionalServices
     *
     * @return $this
     */
    public function setAdditionalServices(?array $additionalServices): static;

    /**
     * @return string
     */
    public function getLabelType(): string;

    /**
     * @param string $labelType
     *
     * @return $this
     */
    public function setLabelType(string $labelType): static;

    /**
     * @return int|null
     */
    public function getDropOffPointId(): ?int;

    /**
     * @param int|null $dropOffPointId
     *
     * @return $this
     */
    public function setDropOffPointId(?int $dropOffPointId): static;

    /**
     * @return string|null
     */
    public function getAddressType(): ?string;

    /**
     * @param string|null $addressType
     *
     * @return $this
     */
    public function setAddressType(?string $addressType): static;

    /**
     * @return bool
     */
    public function getAutoCreateReturnShipment(): bool;

    /**
     * @param bool $autoCreateReturnShipment
     *
     * @return $this
     */
    public function setAutoCreateReturnShipment(bool $autoCreateReturnShipment): static;
}
