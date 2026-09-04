<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api\Data;

interface DropOffPointInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TEMPLATE_NAME = 'template_name';
    public const SENDER_NAME = 'sender_name';
    public const FIRST_NAME = 'first_name';
    public const LAST_NAME = 'last_name';
    public const STREET = 'street';
    public const HOUSE_NUMBER = 'house_number';
    public const POSTAL_CODE = 'postal_code';
    public const CITY = 'city';
    public const COUNTRY_ID = 'country_id';
    public const EMAIL = 'email';
    public const PHONE = 'phone';
    public const SAP_NUMBER = 'sap_number';
    public const PICKUP_TIME_FROM = 'pickup_time_from';
    public const PICKUP_TIME_TO = 'pickup_time_to';
    public const IS_DEFAULT = 'is_default';

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
    public function setEntityId($entityId);

    /**
     * Get profile template name.
     *
     * @return string
     */
    public function getTemplateName(): string;

    /**
     * Set profile template name.
     *
     * @param string $templateName
     *
     * @return $this
     */
    public function setTemplateName(string $templateName): static;

    /**
     * Get sender name.
     *
     * @return string
     */
    public function getSenderName(): string;

    /**
     * Set sender name.
     *
     * @param string $senderName
     *
     * @return $this
     */
    public function setSenderName(string $senderName): static;

    /**
     * Get first name.
     *
     * @return string|null
     */
    public function getFirstName(): ?string;

    /**
     * Set first name.
     *
     * @param string|null $firstName
     *
     * @return $this
     */
    public function setFirstName(?string $firstName): static;

    /**
     * Get last name.
     *
     * @return string|null
     */
    public function getLastName(): ?string;

    /**
     * Set last name.
     *
     * @param string|null $lastName
     *
     * @return $this
     */
    public function setLastName(?string $lastName): static;

    /**
     * Get street.
     *
     * @return string
     */
    public function getStreet(): string;

    /**
     * Set street.
     *
     * @param string $street
     *
     * @return $this
     */
    public function setStreet(string $street): static;

    /**
     * Get house number.
     *
     * @return string
     */
    public function getHouseNumber(): string;

    /**
     * Set house number.
     *
     * @param string $houseNumber
     *
     * @return $this
     */
    public function setHouseNumber(string $houseNumber): static;

    /**
     * Get postal code.
     *
     * @return string
     */
    public function getPostalCode(): string;

    /**
     * Set postal code.
     *
     * @param string $postalCode
     *
     * @return $this
     */
    public function setPostalCode(string $postalCode): static;

    /**
     * Get city.
     *
     * @return string
     */
    public function getCity(): string;

    /**
     * Set city.
     *
     * @param string $city
     *
     * @return $this
     */
    public function setCity(string $city): static;

    /**
     * Get country ID.
     *
     * @return string
     */
    public function getCountryId(): string;

    /**
     * Set country ID.
     *
     * @param string $countryId
     *
     * @return $this
     */
    public function setCountryId(string $countryId): static;

    /**
     * Get email.
     *
     * @return string|null
     */
    public function getEmail(): ?string;

    /**
     * Set email.
     *
     * @param string|null $email
     *
     * @return $this
     */
    public function setEmail(?string $email): static;

    /**
     * Get phone.
     *
     * @return string|null
     */
    public function getPhone(): ?string;

    /**
     * Set phone.
     *
     * @param string|null $phone
     *
     * @return $this
     */
    public function setPhone(?string $phone): static;

    /**
     * Get SAP number.
     *
     * @return string|null
     */
    public function getSapNumber(): ?string;

    /**
     * Set SAP number.
     *
     * @param string|null $sapNumber
     *
     * @return $this
     */
    public function setSapNumber(?string $sapNumber): static;

    /**
     * Get pickup time from.
     *
     * @return string|null
     */
    public function getPickupTimeFrom(): ?string;

    /**
     * Set pickup time from.
     *
     * @param string|null $pickupTimeFrom
     *
     * @return $this
     */
    public function setPickupTimeFrom(?string $pickupTimeFrom): static;

    /**
     * Get pickup time to.
     *
     * @return string|null
     */
    public function getPickupTimeTo(): ?string;

    /**
     * Set pickup time to.
     *
     * @param string|null $pickupTimeTo
     *
     * @return $this
     */
    public function setPickupTimeTo(?string $pickupTimeTo): static;

    /**
     * Get is default.
     *
     * @return bool
     */
    public function getIsDefault(): bool;

    /**
     * Set is default.
     *
     * @param bool $isDefault
     *
     * @return $this
     */
    public function setIsDefault(bool $isDefault): static;
}
