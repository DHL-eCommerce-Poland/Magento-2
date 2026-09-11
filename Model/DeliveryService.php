<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\DeliveryServiceInterface;
use Ideo\Dhl\Model\Config\Source\LabelType;
use Ideo\Dhl\Model\ResourceModel\DeliveryService as DeliveryServiceResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

class DeliveryService extends AbstractModel implements DeliveryServiceInterface
{
    /**
     * Cache tag.
     */
    public const CACHE_TAG = 'dhlparcel_delivery_service';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param SerializerInterface $serializer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @throws LocalizedException
     */
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly SerializerInterface $serializer,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

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
     * Get entity ID.
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        $val = $this->getData(self::ENTITY_ID);
        return $val !== null ? (int)$val : null;
    }

    /**
     * Set entity ID.
     *
     * @param mixed $entityId
     *
     * @return $this
     */
    public function setEntityId($entityId): static
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * Get template name.
     *
     * @return string
     */
    public function getTemplateName(): string
    {
        return (string)$this->getData(self::TEMPLATE_NAME);
    }

    /**
     * Set template name.
     *
     * @param string $templateName
     *
     * @return $this
     */
    public function setTemplateName(string $templateName): static
    {
        return $this->setData(self::TEMPLATE_NAME, $templateName);
    }

    /**
     * Get DHL service code.
     *
     * @return string
     */
    public function getServiceCode(): string
    {
        return (string)$this->getData(self::SERVICE_CODE);
    }

    /**
     * Set DHL service code.
     *
     * @param string $serviceCode
     *
     * @return $this
     */
    public function setServiceCode(string $serviceCode): static
    {
        return $this->setData(self::SERVICE_CODE, $serviceCode);
    }

    /**
     * Get shipping method code.
     *
     * @return string|null
     */
    public function getMethodCode(): ?string
    {
        $val = $this->getData(self::METHOD_CODE);
        if (is_array($val)) {
            return implode(',', array_filter($val));
        }
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set shipping method code.
     *
     * @param string|array|null $methodCode
     *
     * @return $this
     */
    public function setMethodCode(string|array|null $methodCode): static
    {
        if (is_array($methodCode)) {
            $methodCode = implode(',', array_filter($methodCode));
        }
        return $this->setData(self::METHOD_CODE, $methodCode);
    }

    /**
     * Prepare data before saving model.
     *
     * @return $this
     */
    public function beforeSave(): static
    {
        parent::beforeSave();

        $methodCode = $this->getData(self::METHOD_CODE);
        if (is_array($methodCode)) {
            $this->setData(self::METHOD_CODE, implode(',', array_filter($methodCode)));
        }

        if ($this->getPackageType() !== 'PALLET') {
            $this->setHasEuroReturn(false);
        }

        return $this;
    }

    /**
     * Get package type.
     *
     * @return string
     */
    public function getPackageType(): string
    {
        return (string)($this->getData(self::PACKAGE_TYPE) ?: 'PACKAGE');
    }

    /**
     * Set package type.
     *
     * @param string $packageType
     *
     * @return $this
     */
    public function setPackageType(string $packageType): static
    {
        return $this->setData(self::PACKAGE_TYPE, $packageType);
    }

    /**
     * Get weight in KG.
     *
     * @return float
     */
    public function getWeight(): float
    {
        return (float)$this->getData(self::WEIGHT);
    }

    /**
     * Set weight in KG.
     *
     * @param float $weight
     *
     * @return $this
     */
    public function setWeight(float $weight): static
    {
        return $this->setData(self::WEIGHT, $weight);
    }

    /**
     * Get length in CM.
     *
     * @return float
     */
    public function getLength(): float
    {
        return (float)$this->getData(self::LENGTH);
    }

    /**
     * Set length in CM.
     *
     * @param float $length
     *
     * @return $this
     */
    public function setLength(float $length): static
    {
        return $this->setData(self::LENGTH, $length);
    }

    /**
     * Get width in CM.
     *
     * @return float
     */
    public function getWidth(): float
    {
        return (float)$this->getData(self::WIDTH);
    }

    /**
     * Set width in CM.
     *
     * @param float $width
     *
     * @return $this
     */
    public function setWidth(float $width): static
    {
        return $this->setData(self::WIDTH, $width);
    }

    /**
     * Get height in CM.
     *
     * @return float
     */
    public function getHeight(): float
    {
        return (float)$this->getData(self::HEIGHT);
    }

    /**
     * Set height in CM.
     *
     * @param float $height
     *
     * @return $this
     */
    public function setHeight(float $height): static
    {
        return $this->setData(self::HEIGHT, $height);
    }

    /**
     * Get is non standard package flag.
     *
     * @return bool
     */
    public function getIsNonStandard(): bool
    {
        return (bool)$this->getData(self::IS_NON_STANDARD);
    }

    /**
     * Set is non standard package flag.
     *
     * @param bool $isNonStandard
     *
     * @return $this
     */
    public function setIsNonStandard(bool $isNonStandard): static
    {
        return $this->setData(self::IS_NON_STANDARD, $isNonStandard ? 1 : 0);
    }

    /**
     * Get has euro return flag.
     *
     * @return bool
     */
    public function getHasEuroReturn(): bool
    {
        return (bool)$this->getData(self::HAS_EURO_RETURN);
    }

    /**
     * Set has euro return flag.
     *
     * @param bool $hasEuroReturn
     *
     * @return $this
     */
    public function setHasEuroReturn(bool $hasEuroReturn): static
    {
        return $this->setData(self::HAS_EURO_RETURN, $hasEuroReturn ? 1 : 0);
    }

    /**
     * Get package contents description.
     *
     * @return string|null
     */
    public function getContent(): ?string
    {
        $val = $this->getData(self::CONTENT);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set package contents description.
     *
     * @param string|null $content
     *
     * @return $this
     */
    public function setContent(?string $content): static
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * Get special comments.
     *
     * @return string|null
     */
    public function getComment(): ?string
    {
        $val = $this->getData(self::COMMENT);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set special comments.
     *
     * @param string|null $comment
     *
     * @return $this
     */
    public function setComment(?string $comment): static
    {
        return $this->setData(self::COMMENT, $comment);
    }

    /**
     * Get reference number.
     *
     * @return string|null
     */
    public function getReference(): ?string
    {
        $val = $this->getData(self::REFERENCE);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set reference number.
     *
     * @param string|null $reference
     *
     * @return $this
     */
    public function setReference(?string $reference): static
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    /**
     * Get MPK cost center code.
     *
     * @return string|null
     */
    public function getMpk(): ?string
    {
        $val = $this->getData(self::MPK);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set MPK cost center code.
     *
     * @param string|null $mpk
     *
     * @return $this
     */
    public function setMpk(?string $mpk): static
    {
        return $this->setData(self::MPK, $mpk);
    }

    /**
     * Get additional services array.
     *
     * @return array|null
     */
    public function getAdditionalServices(): ?array
    {
        $val = $this->getData(self::ADDITIONAL_SERVICES);
        if (is_array($val)) {
            return $val;
        }
        if (is_string($val) && $val !== '') {
            try {
                $decoded = $this->serializer->unserialize($val);
                return is_array($decoded) ? $decoded : null;
            } catch (\InvalidArgumentException $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Set additional services array.
     *
     * @param array|null $additionalServices
     *
     * @return $this
     */
    public function setAdditionalServices(?array $additionalServices): static
    {
        return $this->setData(
            self::ADDITIONAL_SERVICES,
            $additionalServices !== null ? $this->serializer->serialize($additionalServices) : null
        );
    }

    /**
     * Get label format type.
     *
     * @return string
     */
    public function getLabelType(): string
    {
        return (string)($this->getData(self::LABEL_TYPE) ?: 'BLP');
    }

    /**
     * Set label format type.
     *
     * @param string $labelType
     *
     * @return $this
     */
    public function setLabelType(string $labelType): static
    {
        return $this->setData(self::LABEL_TYPE, $labelType);
    }

    /**
     * Get default drop off point ID.
     *
     * @return int|null
     */
    public function getDropOffPointId(): ?int
    {
        $val = $this->getData(self::DROP_OFF_POINT_ID);
        return ($val !== null && $val !== '') ? (int)$val : null;
    }

    /**
     * Set default drop off point ID.
     *
     * @param int|null $dropOffPointId
     *
     * @return $this
     */
    public function setDropOffPointId(?int $dropOffPointId): static
    {
        return $this->setData(self::DROP_OFF_POINT_ID, $dropOffPointId);
    }

    /**
     * Get address type.
     *
     * @return string|null
     */
    public function getAddressType(): ?string
    {
        $val = $this->getData(self::ADDRESS_TYPE);
        return ($val !== null && $val !== '') ? (string)$val : 'N';
    }

    /**
     * Set address type.
     *
     * @param string|null $addressType
     *
     * @return $this
     */
    public function setAddressType(?string $addressType): static
    {
        return $this->setData(self::ADDRESS_TYPE, $addressType);
    }

    /**
     * Get auto create return shipment flag.
     *
     * @return bool
     */
    public function getAutoCreateReturnShipment(): bool
    {
        return (bool)$this->getData(self::AUTO_CREATE_RETURN_SHIPMENT);
    }

    /**
     * Set auto create return shipment flag.
     *
     * @param bool $autoCreateReturnShipment
     *
     * @return $this
     */
    public function setAutoCreateReturnShipment(bool $autoCreateReturnShipment): static
    {
        return $this->setData(self::AUTO_CREATE_RETURN_SHIPMENT, $autoCreateReturnShipment ? 1 : 0);
    }

    /**
     * Get return label type.
     *
     * @return string|null
     */
    public function getReturnLabelType(): ?string
    {
        $val = $this->getData(self::RETURN_LABEL_TYPE);
        return ($val !== null && $val !== '') ? (string)$val : LabelType::BLP;
    }

    /**
     * Set return label type.
     *
     * @param string|null $returnLabelType
     *
     * @return $this
     */
    public function setReturnLabelType(?string $returnLabelType): static
    {
        return $this->setData(self::RETURN_LABEL_TYPE, $returnLabelType);
    }

    /**
     * Get return QR code type.
     *
     * @return string|null
     */
    public function getReturnQrCodeType(): ?string
    {
        $val = $this->getData(self::RETURN_QR_CODE_TYPE);
        return ($val !== null && $val !== '') ? (string)$val : null;
    }

    /**
     * Set return QR code type.
     *
     * @param string|null $returnQrCodeType
     *
     * @return $this
     */
    public function setReturnQrCodeType(?string $returnQrCodeType): static
    {
        return $this->setData(self::RETURN_QR_CODE_TYPE, $returnQrCodeType);
    }

    /**
     * Get return label expiration days.
     *
     * @return int|null
     */
    public function getReturnLabelExpDays(): ?int
    {
        $val = $this->getData(self::RETURN_LABEL_EXP_DAYS);
        return ($val !== null && $val !== '') ? (int)$val : null;
    }

    /**
     * Set return label expiration days.
     *
     * @param int|null $returnLabelExpDays
     *
     * @return $this
     */
    public function setReturnLabelExpDays(?int $returnLabelExpDays): static
    {
        return $this->setData(self::RETURN_LABEL_EXP_DAYS, $returnLabelExpDays);
    }

    /**
     * Get return book courier method.
     *
     * @return int|null
     */
    public function getReturnBookCourier(): ?int
    {
        $val = $this->getData(self::RETURN_BOOK_COURIER);
        return ($val !== null && $val !== '') ? (int)$val : 0;
    }

    /**
     * Set return book courier method.
     *
     * @param int|null $returnBookCourier
     *
     * @return $this
     */
    public function setReturnBookCourier(?int $returnBookCourier): static
    {
        return $this->setData(self::RETURN_BOOK_COURIER, $returnBookCourier);
    }
}
