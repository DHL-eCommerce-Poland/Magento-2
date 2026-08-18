<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api\Data;

interface DropOffPointInterface
{
    public const ENTITY_ID = 'entity_id';
    public const WEBSITE_ID = 'website_id';
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
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param mixed $entityId
     *
     * @return $this
     */
    public function setEntityId($entityId);

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
    public function getSenderName(): string;

    /**
     * @param string $senderName
 *
     * @return $this
     */
    public function setSenderName(string $senderName): static;

    /**
     * @return string
     */
    public function getStreet(): string;

    /**
     * @param string $street
 *
     * @return $this
     */
    public function setStreet(string $street): static;

    /**
     * @return string
     */
    public function getHouseNumber(): string;

    /**
     * @param string $houseNumber
 *
     * @return $this
     */
    public function setHouseNumber(string $houseNumber): static;

    /**
     * @return string
     */
    public function getPostalCode(): string;

    /**
     * @param string $postalCode
 *
     * @return $this
     */
    public function setPostalCode(string $postalCode): static;

    /**
     * @return string
     */
    public function getCity(): string;

    /**
     * @param string $city
 *
     * @return $this
     */
    public function setCity(string $city): static;

    /**
     * @return string
     */
    public function getCountryId(): string;

    /**
     * @param string $countryId
 *
     * @return $this
     */
    public function setCountryId(string $countryId): static;

    /**
     * @return string|null
     */
    public function getEmail(): ?string;

    /**
     * @param string|null $email
 *
     * @return $this
     */
    public function setEmail(?string $email): static;

    /**
     * @return string|null
     */
    public function getPhone(): ?string;

    /**
     * @param string|null $phone
 *
     * @return $this
     */
    public function setPhone(?string $phone): static;

    /**
     * @return string|null
     */
    public function getSapNumber(): ?string;

    /**
     * @param string|null $sapNumber
 *
     * @return $this
     */
    public function setSapNumber(?string $sapNumber): static;

    /**
     * @return string|null
     */
    public function getFirstName(): ?string;

    /**
     * @param string|null $firstName
     *
     * @return $this
     */
    public function setFirstName(?string $firstName): static;

    /**
     * @return string|null
     */
    public function getLastName(): ?string;

    /**
     * @param string|null $lastName
     *
     * @return $this
     */
    public function setLastName(?string $lastName): static;

    /**
     * @return string|null
     */
    public function getPickupTimeFrom(): ?string;

    /**
     * @param string|null $pickupTimeFrom
     *
     * @return $this
     */
    public function setPickupTimeFrom(?string $pickupTimeFrom): static;

    /**
     * @return string|null
     */
    public function getPickupTimeTo(): ?string;

    /**
     * @param string|null $pickupTimeTo
     *
     * @return $this
     */
    public function setPickupTimeTo(?string $pickupTimeTo): static;

    /**
     * @return bool
     */
    public function getIsDefault(): bool;

    /**
     * @param bool $isDefault
     *
     * @return $this
     */
    public function setIsDefault(bool $isDefault): static;
}
