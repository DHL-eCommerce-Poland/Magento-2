<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint as DropOffPointResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class DropOffPoint extends AbstractModel implements DropOffPointInterface
{
    /**
     * Cache tag.
     */
    public const CACHE_TAG = 'dhlparcel_drop_off_point';

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
        $this->_init(DropOffPointResource::class);
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
     * Get website ID.
     *
     * @return int
     */
    public function getWebsiteId(): int
    {
        return (int)$this->getData(self::WEBSITE_ID);
    }

    /**
     * Set website ID.
     *
     * @param int $websiteId
     *
     * @return $this
     */
    public function setWebsiteId(int $websiteId): static
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * Get profile template name.
     *
     * @return string
     */
    public function getTemplateName(): string
    {
        return (string)$this->getData(self::TEMPLATE_NAME);
    }

    /**
     * Set profile template name.
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
     * Get sender name.
     *
     * @return string
     */
    public function getSenderName(): string
    {
        return (string)$this->getData(self::SENDER_NAME);
    }

    /**
     * Set sender name.
     *
     * @param string $senderName
     *
     * @return $this
     */
    public function setSenderName(string $senderName): static
    {
        return $this->setData(self::SENDER_NAME, $senderName);
    }

    /**
     * Get street.
     *
     * @return string
     */
    public function getStreet(): string
    {
        return (string)$this->getData(self::STREET);
    }

    /**
     * Set street.
     *
     * @param string $street
     *
     * @return $this
     */
    public function setStreet(string $street): static
    {
        return $this->setData(self::STREET, $street);
    }

    /**
     * Get house number.
     *
     * @return string
     */
    public function getHouseNumber(): string
    {
        return (string)$this->getData(self::HOUSE_NUMBER);
    }

    /**
     * Set house number.
     *
     * @param string $houseNumber
     *
     * @return $this
     */
    public function setHouseNumber(string $houseNumber): static
    {
        return $this->setData(self::HOUSE_NUMBER, $houseNumber);
    }

    /**
     * Get postal code.
     *
     * @return string
     */
    public function getPostalCode(): string
    {
        return (string)$this->getData(self::POSTAL_CODE);
    }

    /**
     * Set postal code.
     *
     * @param string $postalCode
     *
     * @return $this
     */
    public function setPostalCode(string $postalCode): static
    {
        return $this->setData(self::POSTAL_CODE, $postalCode);
    }

    /**
     * Get city.
     *
     * @return string
     */
    public function getCity(): string
    {
        return (string)$this->getData(self::CITY);
    }

    /**
     * Set city.
     *
     * @param string $city
     *
     * @return $this
     */
    public function setCity(string $city): static
    {
        return $this->setData(self::CITY, $city);
    }

    /**
     * Get country ID.
     *
     * @return string
     */
    public function getCountryId(): string
    {
        return (string)($this->getData(self::COUNTRY_ID) ?: 'PL');
    }

    /**
     * Set country ID.
     *
     * @param string $countryId
     *
     * @return $this
     */
    public function setCountryId(string $countryId): static
    {
        return $this->setData(self::COUNTRY_ID, $countryId);
    }

    /**
     * Get email.
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        $val = $this->getData(self::EMAIL);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set email.
     *
     * @param string|null $email
     *
     * @return $this
     */
    public function setEmail(?string $email): static
    {
        return $this->setData(self::EMAIL, $email);
    }

    /**
     * Get phone.
     *
     * @return string|null
     */
    public function getPhone(): ?string
    {
        $val = $this->getData(self::PHONE);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set phone.
     *
     * @param string|null $phone
     *
     * @return $this
     */
    public function setPhone(?string $phone): static
    {
        return $this->setData(self::PHONE, $phone);
    }

    /**
     * Get SAP number.
     *
     * @return string|null
     */
    public function getSapNumber(): ?string
    {
        $val = $this->getData(self::SAP_NUMBER);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set SAP number.
     *
     * @param string|null $sapNumber
     *
     * @return $this
     */
    public function setSapNumber(?string $sapNumber): static
    {
        return $this->setData(self::SAP_NUMBER, $sapNumber);
    }

    /**
     * Get first name.
     *
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        $val = $this->getData(self::FIRST_NAME);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set first name.
     *
     * @param string|null $firstName
     *
     * @return $this
     */
    public function setFirstName(?string $firstName): static
    {
        return $this->setData(self::FIRST_NAME, $firstName);
    }

    /**
     * Get last name.
     *
     * @return string|null
     */
    public function getLastName(): ?string
    {
        $val = $this->getData(self::LAST_NAME);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set last name.
     *
     * @param string|null $lastName
     *
     * @return $this
     */
    public function setLastName(?string $lastName): static
    {
        return $this->setData(self::LAST_NAME, $lastName);
    }

    /**
     * Get pickup time from.
     *
     * @return string|null
     */
    public function getPickupTimeFrom(): ?string
    {
        $val = $this->getData(self::PICKUP_TIME_FROM);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set pickup time from.
     *
     * @param string|null $pickupTimeFrom
     *
     * @return $this
     */
    public function setPickupTimeFrom(?string $pickupTimeFrom): static
    {
        return $this->setData(self::PICKUP_TIME_FROM, $pickupTimeFrom);
    }

    /**
     * Get pickup time to.
     *
     * @return string|null
     */
    public function getPickupTimeTo(): ?string
    {
        $val = $this->getData(self::PICKUP_TIME_TO);
        return $val !== null ? (string)$val : null;
    }

    /**
     * Set pickup time to.
     *
     * @param string|null $pickupTimeTo
     *
     * @return $this
     */
    public function setPickupTimeTo(?string $pickupTimeTo): static
    {
        return $this->setData(self::PICKUP_TIME_TO, $pickupTimeTo);
    }

    /**
     * Get is default.
     *
     * @return bool
     */
    public function getIsDefault(): bool
    {
        return (bool)$this->getData(self::IS_DEFAULT);
    }

    /**
     * Set is default.
     *
     * @param bool $isDefault
     *
     * @return $this
     */
    public function setIsDefault(bool $isDefault): static
    {
        return $this->setData(self::IS_DEFAULT, $isDefault ? 1 : 0);
    }
}
