<?php

declare(strict_types=1);

namespace Ideo\Dhl\ViewModel\Adminhtml\Order\Create;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\MethodCatalog;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Address;

class ShippingMethodFormViewModel implements ArgumentInterface
{
    /**
     * @param MethodCatalog $methodCatalog
     */
    public function __construct(
        private readonly MethodCatalog $methodCatalog
    ) {
    }

    /**
     * Determine whether the shipping rate corresponds to a DHL Service Point method.
     *
     * @param string $carrierCode
     * @param string $methodCode
     * @param string $rateCode
     *
     * @return bool
     */
    public function isDhlServicePointMethod(string $carrierCode, string $methodCode, string $rateCode): bool
    {
        if ($carrierCode !== Carrier::CODE) {
            return false;
        }

        $cleanMethod = str_starts_with($methodCode, Carrier::CODE_PREFIX)
            ? substr($methodCode, strlen(Carrier::CODE_PREFIX))
            : $methodCode;
        $cleanCode = str_starts_with($rateCode, Carrier::CODE_PREFIX)
            ? substr($rateCode, strlen(Carrier::CODE_PREFIX))
            : $rateCode;

        return $this->methodCatalog->isServicePointMethod($cleanMethod)
            || $this->methodCatalog->isServicePointMethod($cleanCode);
    }

    /**
     * Get DHL service point ID from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getServicePointId(?Address $quoteAddress): string
    {
        return $this->decodeValue($quoteAddress ? (string) $quoteAddress->getData('dhl_service_point_id') : '');
    }

    /**
     * Get DHL service point name from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getServicePointName(?Address $quoteAddress): string
    {
        return $this->decodeValue($quoteAddress ? (string) $quoteAddress->getData('dhl_service_point_name') : '');
    }

    /**
     * Get DHL service point street from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getServicePointStreet(?Address $quoteAddress): string
    {
        return $this->decodeValue($quoteAddress ? (string) $quoteAddress->getData('dhl_service_point_street') : '');
    }

    /**
     * Get DHL service point postcode from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getServicePointPostcode(?Address $quoteAddress): string
    {
        return $this->decodeValue($quoteAddress ? (string) $quoteAddress->getData('dhl_service_point_postcode') : '');
    }

    /**
     * Get DHL service point city from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getServicePointCity(?Address $quoteAddress): string
    {
        return $this->decodeValue($quoteAddress ? (string) $quoteAddress->getData('dhl_service_point_city') : '');
    }

    /**
     * Get Packstation flag from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return int
     */
    public function getIsPackstation(?Address $quoteAddress): int
    {
        return (int) ($quoteAddress ? $quoteAddress->getData('dhl_is_packstation') : 0);
    }

    /**
     * Get Postfiliale flag from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return int
     */
    public function getIsPostfiliale(?Address $quoteAddress): int
    {
        return (int) ($quoteAddress ? $quoteAddress->getData('dhl_is_postfiliale') : 0);
    }

    /**
     * Get Postnummer from quote address.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getPostnummer(?Address $quoteAddress): string
    {
        return $this->decodeValue($quoteAddress ? (string) $quoteAddress->getData('dhl_postnummer') : '');
    }

    /**
     * Get shipping address country ID or fallback to PL.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getCountryId(?Address $quoteAddress): string
    {
        return $quoteAddress ? (string) ($quoteAddress->getCountryId() ?: 'PL') : 'PL';
    }

    /**
     * Format display text for selected service point.
     *
     * @param Address|null $quoteAddress
     *
     * @return string
     */
    public function getFormattedDisplayText(?Address $quoteAddress): string
    {
        $id = $this->getServicePointId($quoteAddress);
        if ($id === '') {
            return '';
        }

        $name = $this->getServicePointName($quoteAddress);
        $street = $this->getServicePointStreet($quoteAddress);
        $postcode = $this->getServicePointPostcode($quoteAddress);
        $city = $this->getServicePointCity($quoteAddress);

        $parts = [];
        if ($name !== '') {
            $parts[] = $name;
        }
        if ($street !== '' && $street !== $name) {
            $parts[] = $street;
        }
        $pcCity = trim($postcode . ' ' . $city);
        if ($pcCity !== '' && !str_contains($name, $pcCity) && !str_contains($street, $pcCity)) {
            $parts[] = $pcCity;
        }

        $uniqueParts = array_values(array_unique($parts));

        return '(' . $id . ') ' . implode(', ', $uniqueParts);
    }

    /**
     * Decode HTML entities from string values.
     *
     * @param string $value
     *
     * @return string
     */
    private function decodeValue(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
