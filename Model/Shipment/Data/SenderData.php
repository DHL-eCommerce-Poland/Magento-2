<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment\Data;

class SenderData
{
    /**
     * @param string $payer
     * @param string $sapNumber
     * @param string $name
     * @param string $firstName
     * @param string $lastName
     * @param string $street
     * @param string $houseNumber
     * @param string $flatNumber
     * @param string $city
     * @param string $postalCode
     * @param string $country
     * @param string $phone
     * @param string $email
     */
    public function __construct(
        private string $payer = 'SENDER',
        private string $sapNumber = '',
        private string $name = '',
        private string $firstName = '',
        private string $lastName = '',
        private string $street = '',
        private string $houseNumber = '',
        private string $flatNumber = '',
        private string $city = '',
        private string $postalCode = '',
        private string $country = 'PL',
        private string $phone = '',
        private string $email = ''
    ) {
    }

    /**
     * Get payer type (SENDER, RECIPIENT, USER).
     *
     * @return string
     */
    public function getPayer(): string
    {
        return $this->payer;
    }

    /**
     * Get DHL SAP account number.
     *
     * @return string
     */
    public function getSapNumber(): string
    {
        return $this->sapNumber;
    }

    /**
     * Get sender company name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
     * Get street name.
     *
     * @return string
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * Get house number.
     *
     * @return string
     */
    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }

    /**
     * Get flat/apartment number.
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
     * Get two-letter country ISO code.
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
     * Convert sender object to array format.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'payer' => $this->payer,
            'sapNumber' => $this->sapNumber,
            'name' => $this->name,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'street' => $this->street,
            'houseNumber' => $this->houseNumber,
            'flatNumber' => $this->flatNumber,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    /**
     * Create SenderData object from array representation.
     *
     * @param array $data
     *
     * @return self
     */
    // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['payer'] ?? 'SENDER'),
            (string)($data['sapNumber'] ?? $data['sap_number'] ?? ''),
            (string)($data['name'] ?? $data['sender_name'] ?? ''),
            (string)($data['firstName'] ?? $data['first_name'] ?? ''),
            (string)($data['lastName'] ?? $data['last_name'] ?? ''),
            (string)($data['street'] ?? ''),
            (string)($data['houseNumber'] ?? $data['house_number'] ?? ''),
            (string)($data['flatNumber'] ?? $data['flat_number'] ?? ''),
            (string)($data['city'] ?? ''),
            (string)($data['postalCode'] ?? $data['postal_code'] ?? ''),
            (string)($data['country'] ?? 'PL'),
            (string)($data['phone'] ?? ''),
            (string)($data['email'] ?? '')
        );
    }
}
