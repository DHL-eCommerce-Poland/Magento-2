<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment\Data;

class RecipientData
{
    /**
     * @param string $name
     * @param string $type
     * @param string $firstName
     * @param string $lastName
     * @param string $contactPerson
     * @param string $street
     * @param string $houseNumber
     * @param string $flatNumber
     * @param string $city
     * @param string $postalCode
     * @param string $country
     * @param string $phone
     * @param string $email
     * @param string|null $popId
     * @param string|null $popName
     * @param bool $isPackstation
     * @param bool $isPostfiliale
     * @param string|null $postnummer
     */
    public function __construct(
        private string $name = '',
        private string $type = 'C',
        private string $firstName = '',
        private string $lastName = '',
        private string $contactPerson = '',
        private string $street = '',
        private string $houseNumber = '',
        private string $flatNumber = '',
        private string $city = '',
        private string $postalCode = '',
        private string $country = 'PL',
        private string $phone = '',
        private string $email = '',
        private ?string $popId = null,
        private ?string $popName = null,
        private bool $isPackstation = false,
        private bool $isPostfiliale = false,
        private ?string $postnummer = null
    ) {
    }

    /**
     * Get company name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get recipient address type (B - Business, C - Consumer/Private).
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get first name.
     *
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * Get last name.
     *
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Get full contact person name.
     *
     * @return string
     */
    public function getContactPerson(): string
    {
        return $this->contactPerson;
    }

    /**
     * Get street name.
     *
     * @return string
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * Get building/house number.
     *
     * @return string
     */
    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }

    /**
     * Get apartment/flat number.
     *
     * @return string
     */
    public function getFlatNumber(): string
    {
        return $this->flatNumber;
    }

    /**
     * Get city name.
     *
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * Get postal code.
     *
     * @return string
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * Get two-letter country ISO code (e.g. PL).
     *
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * Get phone number.
     *
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Get email address.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get POP Service Point ID.
     *
     * @return string|null
     */
    public function getPopId(): ?string
    {
        return $this->popId;
    }

    /**
     * Get POP Service Point Name and Address.
     *
     * @return string|null
     */
    public function getPopName(): ?string
    {
        return $this->popName;
    }

    /**
     * Check if recipient address is a Packstation (DE locker).
     *
     * @return bool
     */
    public function isPackstation(): bool
    {
        return $this->isPackstation;
    }

    /**
     * Check if recipient address is a Postfiliale.
     *
     * @return bool
     */
    public function isPostfiliale(): bool
    {
        return $this->isPostfiliale;
    }

    /**
     * Get German customer Postnummer for DE Packstation.
     *
     * @return string|null
     */
    public function getPostnummer(): ?string
    {
        return $this->postnummer;
    }

    /**
     * Convert recipient object to array format.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'contactPerson' => $this->contactPerson,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'flatNumber' => $this->flatNumber,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'popId' => $this->popId,
            'popName' => $this->popName,
            'isPackstation' => $this->isPackstation,
            'isPostfiliale' => $this->isPostfiliale,
            'postnummer' => $this->postnummer,
        ];
    }

    /**
     * Create RecipientData object from array representation.
     *
     * @param array $data
     *
     * @return self
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function fromArray(array $data): self
    {
        $name = (string)($data['name'] ?? '');
        $rawType = strtoupper(trim((string)($data['type'] ?? $data['addressType'] ?? '')));
        $type = !empty($rawType)
            ? $rawType
            : (trim($name) !== '' ? 'B' : 'C');

        $isPackstation = !empty($data['isPackstation']);
        $isPostfiliale = !empty($data['isPostfiliale']);

        if (isset($data['mapPointType'])) {
            $mapPointType = strtolower(trim((string)$data['mapPointType']));
            if ($mapPointType === 'packstation') {
                $isPackstation = true;
                $isPostfiliale = false;
            } elseif ($mapPointType === 'parcelshop') {
                $isPackstation = false;
                $isPostfiliale = true;
            } elseif ($mapPointType === 'courier' || $mapPointType === '') {
                $isPackstation = false;
                $isPostfiliale = false;
            }
        }

        return new self(
            $name,
            $type,
            (string)($data['firstName'] ?? ''),
            (string)($data['lastName'] ?? ''),
            (string)($data['contactPerson'] ?? ''),
            (string)($data['street'] ?? ''),
            (string)($data['houseNumber'] ?? ''),
            (string)($data['flatNumber'] ?? ''),
            (string)($data['city'] ?? ''),
            (string)($data['postalCode'] ?? ''),
            (string)($data['country'] ?? 'PL'),
            (string)($data['phone'] ?? ''),
            (string)($data['email'] ?? ''),
            isset($data['popId']) ? (string)$data['popId'] : null,
            isset($data['popName']) ? (string)$data['popName'] : null,
            $isPackstation,
            $isPostfiliale,
            isset($data['postnummer']) ? (string)$data['postnummer'] : null
        );
    }
}
