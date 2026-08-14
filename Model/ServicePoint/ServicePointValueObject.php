<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ServicePoint;

class ServicePointValueObject
{
    /**
     * @param string $id
     * @param string $name
     * @param string $street
     * @param string $postcode
     * @param string $city
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name = '',
        private readonly string $street = '',
        private readonly string $postcode = '',
        private readonly string $city = ''
    ) {
    }

    /**
     * Get DHL SAP ID / service point account number.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get service point name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get service point street.
     *
     * @return string
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * Get service point postal code.
     *
     * @return string
     */
    public function getPostcode(): string
    {
        return $this->postcode;
    }

    /**
     * Get service point city.
     *
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * Check if the service point contains a non-empty ID.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return trim($this->id) !== '';
    }

    /**
     * Convert value object into array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'dhl_service_point_id' => $this->id,
            'dhl_service_point_name' => $this->name,
            'dhl_service_point_street' => $this->street,
            'dhl_service_point_postcode' => $this->postcode,
            'dhl_service_point_city' => $this->city,
        ];
    }
}
