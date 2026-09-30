<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Model\ResourceModel\CustomsTemplate as CustomsTemplateResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

class CustomsTemplate extends AbstractModel
{
    public const CACHE_TAG = 'dhlpoland_customs_template';

    public const TEMPLATE_NAME = 'template_name';
    public const CUSTOMS_TYPE = 'customs_type';
    public const CATEGORY_OF_ITEM = 'category_of_item';
    public const CURRENCY = 'currency';
    public const DEFAULT_TARIFF_CODE = 'default_tariff_code';
    public const SHIPPER_EORI = 'shipper_eori';
    public const SHIPPER_NIP = 'shipper_nip';
    public const SHIPPER_VAT_NO = 'shipper_vat_no';
    public const COUNTRY_OF_ORIGIN = 'country_of_origin';
    public const COSTS_OF_SHIPMENT = 'costs_of_shipment';
    public const GROSS_WEIGHT = 'gross_weight';
    public const RECEIVER_EORI = 'receiver_eori';
    public const RECEIVER_VAT_NO = 'receiver_vat_no';
    public const CONTACT_FIRST_NAME = 'contact_first_name';
    public const CONTACT_LAST_NAME = 'contact_last_name';
    public const ADDITIONAL_INFO = 'additional_info';
    public const NOT_EXCEED_VALUE = 'not_exceed_value';
    public const NOT_PROHIBITED_GOODS = 'not_prohibited_goods';
    public const NOT_RESTRICTED_GOODS = 'not_restricted_goods';
    public const CUSTOMS_ITEMS = 'customs_items';
    public const COUNTRY_ID = 'country_id';

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
        $this->_init(CustomsTemplateResource::class);
    }

    /**
     * Get Template Name.
     *
     * @return string|null
     */
    public function getTemplateName(): ?string
    {
        return $this->getData(self::TEMPLATE_NAME);
    }

    /**
     * Set Template Name.
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
     * Get Customs Type.
     *
     * @return string|null
     */
    public function getCustomsType(): ?string
    {
        return $this->getData(self::CUSTOMS_TYPE);
    }

    /**
     * Set Customs Type.
     *
     * @param string $customsType
     *
     * @return $this
     */
    public function setCustomsType(string $customsType): static
    {
        return $this->setData(self::CUSTOMS_TYPE, $customsType);
    }

    /**
     * Get Category Of Item.
     *
     * @return string|null
     */
    public function getCategoryOfItem(): ?string
    {
        return $this->getData(self::CATEGORY_OF_ITEM);
    }

    /**
     * Set Category Of Item.
     *
     * @param string $categoryOfItem
     *
     * @return $this
     */
    public function setCategoryOfItem(string $categoryOfItem): static
    {
        return $this->setData(self::CATEGORY_OF_ITEM, $categoryOfItem);
    }

    /**
     * Get Currency.
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->getData(self::CURRENCY);
    }

    /**
     * Set Currency.
     *
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency(string $currency): static
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    /**
     * Get Default Tariff Code (HS Code).
     *
     * @return string|null
     */
    public function getDefaultTariffCode(): ?string
    {
        return $this->getData(self::DEFAULT_TARIFF_CODE);
    }

    /**
     * Set Default Tariff Code.
     *
     * @param string|null $defaultTariffCode
     *
     * @return $this
     */
    public function setDefaultTariffCode(?string $defaultTariffCode): static
    {
        return $this->setData(self::DEFAULT_TARIFF_CODE, $defaultTariffCode);
    }

    /**
     * Get Shipper EORI.
     *
     * @return string|null
     */
    public function getShipperEori(): ?string
    {
        return $this->getData(self::SHIPPER_EORI);
    }

    /**
     * Set Shipper EORI.
     *
     * @param string|null $shipperEori
     *
     * @return $this
     */
    public function setShipperEori(?string $shipperEori): static
    {
        return $this->setData(self::SHIPPER_EORI, $shipperEori);
    }

    /**
     * Get Shipper NIP.
     *
     * @return string|null
     */
    public function getShipperNip(): ?string
    {
        return $this->getData(self::SHIPPER_NIP);
    }

    /**
     * Set Shipper NIP.
     *
     * @param string|null $shipperNip
     *
     * @return $this
     */
    public function setShipperNip(?string $shipperNip): static
    {
        return $this->setData(self::SHIPPER_NIP, $shipperNip);
    }

    /**
     * Get Shipper VAT No.
     *
     * @return string|null
     */
    public function getShipperVatNo(): ?string
    {
        return $this->getData(self::SHIPPER_VAT_NO);
    }

    /**
     * Set Shipper VAT No.
     *
     * @param string|null $shipperVatNo
     *
     * @return $this
     */
    public function setShipperVatNo(?string $shipperVatNo): static
    {
        return $this->setData(self::SHIPPER_VAT_NO, $shipperVatNo);
    }

    /**
     * Get Country Of Origin.
     *
     * @return string|null
     */
    public function getCountryOfOrigin(): ?string
    {
        return $this->getData(self::COUNTRY_OF_ORIGIN);
    }

    /**
     * Set Country Of Origin.
     *
     * @param string|null $countryOfOrigin
     *
     * @return $this
     */
    public function setCountryOfOrigin(?string $countryOfOrigin): static
    {
        return $this->setData(self::COUNTRY_OF_ORIGIN, $countryOfOrigin);
    }

    /**
     * Get Costs Of Shipment.
     *
     * @return float|null
     */
    public function getCostsOfShipment(): ?float
    {
        $val = $this->getData(self::COSTS_OF_SHIPMENT);
        if ($val === null || $val === '') {
            return null;
        }
        if (is_string($val)) {
            $val = str_replace(',', '.', trim($val));
        }
        return (float)$val;
    }

    /**
     * Set Costs Of Shipment.
     *
     * @param float|null $costsOfShipment
     *
     * @return $this
     */
    public function setCostsOfShipment(?float $costsOfShipment): static
    {
        return $this->setData(self::COSTS_OF_SHIPMENT, $costsOfShipment);
    }

    /**
     * Get Gross Weight.
     *
     * @return float|null
     */
    public function getGrossWeight(): ?float
    {
        $val = $this->getData(self::GROSS_WEIGHT);
        if ($val === null || $val === '') {
            return null;
        }
        if (is_string($val)) {
            $val = str_replace(',', '.', trim($val));
        }
        return (float)$val;
    }

    /**
     * Set Gross Weight.
     *
     * @param float|null $grossWeight
     *
     * @return $this
     */
    public function setGrossWeight(?float $grossWeight): static
    {
        return $this->setData(self::GROSS_WEIGHT, $grossWeight);
    }

    /**
     * Get Receiver EORI.
     *
     * @return string|null
     */
    public function getReceiverEori(): ?string
    {
        return $this->getData(self::RECEIVER_EORI);
    }

    /**
     * Set Receiver EORI.
     *
     * @param string|null $receiverEori
     *
     * @return $this
     */
    public function setReceiverEori(?string $receiverEori): static
    {
        return $this->setData(self::RECEIVER_EORI, $receiverEori);
    }

    /**
     * Get Receiver VAT No.
     *
     * @return string|null
     */
    public function getReceiverVatNo(): ?string
    {
        return $this->getData(self::RECEIVER_VAT_NO);
    }

    /**
     * Set Receiver VAT No.
     *
     * @param string|null $receiverVatNo
     *
     * @return $this
     */
    public function setReceiverVatNo(?string $receiverVatNo): static
    {
        return $this->setData(self::RECEIVER_VAT_NO, $receiverVatNo);
    }

    /**
     * Get Contact First Name.
     *
     * @return string|null
     */
    public function getContactFirstName(): ?string
    {
        return $this->getData(self::CONTACT_FIRST_NAME);
    }

    /**
     * Set Contact First Name.
     *
     * @param string|null $firstName
     *
     * @return $this
     */
    public function setContactFirstName(?string $firstName): static
    {
        return $this->setData(self::CONTACT_FIRST_NAME, $firstName);
    }

    /**
     * Get Contact Last Name.
     *
     * @return string|null
     */
    public function getContactLastName(): ?string
    {
        return $this->getData(self::CONTACT_LAST_NAME);
    }

    /**
     * Set Contact Last Name.
     *
     * @param string|null $lastName
     *
     * @return $this
     */
    public function setContactLastName(?string $lastName): static
    {
        return $this->setData(self::CONTACT_LAST_NAME, $lastName);
    }

    /**
     * Get Additional Info.
     *
     * @return string|null
     */
    public function getAdditionalInfo(): ?string
    {
        return $this->getData(self::ADDITIONAL_INFO);
    }

    /**
     * Set Additional Info.
     *
     * @param string|null $additionalInfo
     *
     * @return $this
     */
    public function setAdditionalInfo(?string $additionalInfo): static
    {
        return $this->setData(self::ADDITIONAL_INFO, $additionalInfo);
    }

    /**
     * Get Not Exceed Value agreement flag.
     *
     * @return bool|null
     */
    public function getNotExceedValue(): ?bool
    {
        $val = $this->getData(self::NOT_EXCEED_VALUE);
        return $val !== null ? (bool)$val : null;
    }

    /**
     * Set Not Exceed Value agreement flag.
     *
     * @param bool|int|null $flag
     *
     * @return $this
     */
    public function setNotExceedValue(bool|int|null $flag): static
    {
        return $this->setData(self::NOT_EXCEED_VALUE, $flag !== null ? (int)(bool)$flag : null);
    }

    /**
     * Get Not Prohibited Goods agreement flag.
     *
     * @return bool|null
     */
    public function getNotProhibitedGoods(): ?bool
    {
        $val = $this->getData(self::NOT_PROHIBITED_GOODS);
        return $val !== null ? (bool)$val : null;
    }

    /**
     * Set Not Prohibited Goods agreement flag.
     *
     * @param bool|int|null $flag
     *
     * @return $this
     */
    public function setNotProhibitedGoods(bool|int|null $flag): static
    {
        return $this->setData(self::NOT_PROHIBITED_GOODS, $flag !== null ? (int)(bool)$flag : null);
    }

    /**
     * Get Not Restricted Goods agreement flag.
     *
     * @return bool|null
     */
    public function getNotRestrictedGoods(): ?bool
    {
        $val = $this->getData(self::NOT_RESTRICTED_GOODS);
        return $val !== null ? (bool)$val : null;
    }

    /**
     * Set Not Restricted Goods agreement flag.
     *
     * @param bool|int|null $flag
     *
     * @return $this
     */
    public function setNotRestrictedGoods(bool|int|null $flag): static
    {
        return $this->setData(self::NOT_RESTRICTED_GOODS, $flag !== null ? (int)(bool)$flag : null);
    }

    /**
     * Get Customs Items (raw JSON or null).
     *
     * @return string|array|null
     */
    public function getCustomsItems(): string|array|null
    {
        return $this->getData(self::CUSTOMS_ITEMS);
    }

    /**
     * Set Customs Items.
     *
     * @param string|array|null $items
     *
     * @return $this
     */
    public function setCustomsItems(string|array|null $items): static
    {
        if (is_array($items)) {
            $items = $this->serializer->serialize($items);
        }
        return $this->setData(self::CUSTOMS_ITEMS, $items);
    }

    /**
     * Get Destination Country Codes (comma-separated or null).
     *
     * @return string|null
     */
    public function getCountryId(): ?string
    {
        return $this->getData(self::COUNTRY_ID);
    }

    /**
     * Set Destination Country Codes.
     *
     * @param string|array|null $countryId
     *
     * @return $this
     */
    public function setCountryId(string|array|null $countryId): static
    {
        if (is_array($countryId)) {
            $countryId = implode(',', array_filter(array_map('trim', $countryId)));
        }
        return $this->setData(self::COUNTRY_ID, $countryId !== '' ? $countryId : null);
    }
}
