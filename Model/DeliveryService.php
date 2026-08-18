<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\DeliveryServiceInterface;
use Ideo\Dhl\Model\ResourceModel\DeliveryService as DeliveryServiceResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class DeliveryService extends AbstractModel implements DeliveryServiceInterface
{
    /**
     * Cache tag.
     */
    public const CACHE_TAG = 'ideo_dhl_delivery_service';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * Initialize resource model.
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(DeliveryServiceResource::class);
    }

    /**
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        $val = $this->getData(self::ENTITY_ID);
        return $val !== null ? (int)$val : null;
    }

    /**
     * @param mixed $entityId
     *
     * @return $this
     */
    public function setEntityId($entityId): static
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @return int
     */
    public function getWebsiteId(): int
    {
        return (int)$this->getData(self::WEBSITE_ID);
    }

    /**
     * @param int $websiteId
     *
     * @return $this
     */
    public function setWebsiteId(int $websiteId): static
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * @return string
     */
    public function getTemplateName(): string
    {
        return (string)$this->getData(self::TEMPLATE_NAME);
    }

    /**
     * @param string $templateName
     *
     * @return $this
     */
    public function setTemplateName(string $templateName): static
    {
        return $this->setData(self::TEMPLATE_NAME, $templateName);
    }

    /**
     * @return string
     */
    public function getServiceCode(): string
    {
        return (string)$this->getData(self::SERVICE_CODE);
    }

    /**
     * @param string $serviceCode
     *
     * @return $this
     */
    public function setServiceCode(string $serviceCode): static
    {
        return $this->setData(self::SERVICE_CODE, $serviceCode);
    }

    /**
     * @return string|null
     */
    public function getMethodCode(): ?string
    {
        $val = $this->getData(self::METHOD_CODE);
        return $val !== null ? (string)$val : null;
    }

    /**
     * @param string|null $methodCode
     *
     * @return $this
     */
    public function setMethodCode(?string $methodCode): static
    {
        return $this->setData(self::METHOD_CODE, $methodCode);
    }

    /**
     * @return string
     */
    public function getPackageType(): string
    {
        return (string)($this->getData(self::PACKAGE_TYPE) ?: 'PACKAGE');
    }

    /**
     * @param string $packageType
     *
     * @return $this
     */
    public function setPackageType(string $packageType): static
    {
        return $this->setData(self::PACKAGE_TYPE, $packageType);
    }

    /**
     * @return float
     */
    public function getWeight(): float
    {
        return (float)$this->getData(self::WEIGHT);
    }

    /**
     * @param float $weight
     *
     * @return $this
     */
    public function setWeight(float $weight): static
    {
        return $this->setData(self::WEIGHT, $weight);
    }

    /**
     * @return float
     */
    public function getLength(): float
    {
        return (float)$this->getData(self::LENGTH);
    }

    /**
     * @param float $length
     *
     * @return $this
     */
    public function setLength(float $length): static
    {
        return $this->setData(self::LENGTH, $length);
    }

    /**
     * @return float
     */
    public function getWidth(): float
    {
        return (float)$this->getData(self::WIDTH);
    }

    /**
     * @param float $width
     *
     * @return $this
     */
    public function setWidth(float $width): static
    {
        return $this->setData(self::WIDTH, $width);
    }

    /**
     * @return float
     */
    public function getHeight(): float
    {
        return (float)$this->getData(self::HEIGHT);
    }

    /**
     * @param float $height
     *
     * @return $this
     */
    public function setHeight(float $height): static
    {
        return $this->setData(self::HEIGHT, $height);
    }

    /**
     * @return bool
     */
    public function getIsNonStandard(): bool
    {
        return (bool)$this->getData(self::IS_NON_STANDARD);
    }

    /**
     * @param bool $isNonStandard
     *
     * @return $this
     */
    public function setIsNonStandard(bool $isNonStandard): static
    {
        return $this->setData(self::IS_NON_STANDARD, $isNonStandard ? 1 : 0);
    }

    /**
     * @return bool
     */
    public function getHasEuroReturn(): bool
    {
        return (bool)$this->getData(self::HAS_EURO_RETURN);
    }

    /**
     * @param bool $hasEuroReturn
     *
     * @return $this
     */
    public function setHasEuroReturn(bool $hasEuroReturn): static
    {
        return $this->setData(self::HAS_EURO_RETURN, $hasEuroReturn ? 1 : 0);
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        $val = $this->getData(self::CONTENT);
        return $val !== null ? (string)$val : null;
    }

    /**
     * @param string|null $content
     *
     * @return $this
     */
    public function setContent(?string $content): static
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        $val = $this->getData(self::COMMENT);
        return $val !== null ? (string)$val : null;
    }

    /**
     * @param string|null $comment
     *
     * @return $this
     */
    public function setComment(?string $comment): static
    {
        return $this->setData(self::COMMENT, $comment);
    }

    /**
     * @return string|null
     */
    public function getReference(): ?string
    {
        $val = $this->getData(self::REFERENCE);
        return $val !== null ? (string)$val : null;
    }

    /**
     * @param string|null $reference
     *
     * @return $this
     */
    public function setReference(?string $reference): static
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    /**
     * @return string|null
     */
    public function getMpk(): ?string
    {
        $val = $this->getData(self::MPK);
        return $val !== null ? (string)$val : null;
    }

    /**
     * @param string|null $mpk
     *
     * @return $this
     */
    public function setMpk(?string $mpk): static
    {
        return $this->setData(self::MPK, $mpk);
    }

    /**
     * @return array|null
     */
    public function getAdditionalServices(): ?array
    {
        $val = $this->getData(self::ADDITIONAL_SERVICES);
        if (is_array($val)) {
            return $val;
        }
        if (is_string($val) && $val !== '') {
            $decoded = json_decode($val, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * @param array|null $additionalServices
     *
     * @return $this
     */
    public function setAdditionalServices(?array $additionalServices): static
    {
        return $this->setData(
            self::ADDITIONAL_SERVICES,
            $additionalServices !== null ? json_encode($additionalServices) : null
        );
    }

    /**
     * @return string
     */
    public function getLabelType(): string
    {
        return (string)($this->getData(self::LABEL_TYPE) ?: 'BLP');
    }

    /**
     * @param string $labelType
     *
     * @return $this
     */
    public function setLabelType(string $labelType): static
    {
        return $this->setData(self::LABEL_TYPE, $labelType);
    }

    /**
     * @return int|null
     */
    public function getDropOffPointId(): ?int
    {
        $val = $this->getData(self::DROP_OFF_POINT_ID);
        return ($val !== null && $val !== '') ? (int)$val : null;
    }

    /**
     * @param int|null $dropOffPointId
     *
     * @return $this
     */
    public function setDropOffPointId(?int $dropOffPointId): static
    {
        return $this->setData(self::DROP_OFF_POINT_ID, $dropOffPointId);
    }

    /**
     * @return string|null
     */
    public function getAddressType(): ?string
    {
        $val = $this->getData(self::ADDRESS_TYPE);
        return ($val !== null && $val !== '') ? (string)$val : null;
    }

    /**
     * @param string|null $addressType
     *
     * @return $this
     */
    public function setAddressType(?string $addressType): static
    {
        return $this->setData(self::ADDRESS_TYPE, $addressType);
    }

    /**
     * @return bool
     */
    public function getAutoCreateReturnShipment(): bool
    {
        return (bool)$this->getData(self::AUTO_CREATE_RETURN_SHIPMENT);
    }

    /**
     * @param bool $autoCreateReturnShipment
     *
     * @return $this
     */
    public function setAutoCreateReturnShipment(bool $autoCreateReturnShipment): static
    {
        return $this->setData(self::AUTO_CREATE_RETURN_SHIPMENT, $autoCreateReturnShipment ? 1 : 0);
    }
}
