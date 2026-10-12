<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Locale\ListsInterface;

class CustomsCurrency implements OptionSourceInterface
{
    public const CURRENCY_GBP = 'GBP';
    public const CURRENCY_PLN = 'PLN';
    public const CURRENCY_EUR = 'EUR';
    public const CURRENCY_CHF = 'CHF';
    public const CURRENCY_USD = 'USD';

    public const ALL_CUSTOMS_CURRENCIES = [
        self::CURRENCY_GBP,
        self::CURRENCY_PLN,
        self::CURRENCY_EUR,
        self::CURRENCY_CHF,
        self::CURRENCY_USD,
    ];

    public const ALLOWED_CURRENCIES_BY_TYPE = [
        CustomsType::TYPE_INDIVIDUAL => [self::CURRENCY_GBP, self::CURRENCY_EUR],
        CustomsType::TYPE_SIMPLIFIED => [
            self::CURRENCY_GBP,
            self::CURRENCY_EUR,
            self::CURRENCY_USD,
            self::CURRENCY_PLN,
            self::CURRENCY_CHF,
        ],
        CustomsType::TYPE_SHORT_DECLARATION => [self::CURRENCY_GBP, self::CURRENCY_EUR, self::CURRENCY_USD],
    ];

    /**
     * @param ListsInterface $localeLists
     */
    public function __construct(
        private readonly ListsInterface $localeLists
    ) {
    }

    /**
     * Get all available currencies for customs clearance filtered from Magento locale list.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return $this->filterCurrencies(self::ALL_CUSTOMS_CURRENCIES);
    }

    /**
     * Get allowed currency codes for a specific customs clearance type.
     *
     * @param string $customsType
     * @return array
     */
    public function getCurrenciesForType(string $customsType): array
    {
        return self::ALLOWED_CURRENCIES_BY_TYPE[$customsType] ?? [];
    }

    /**
     * Check if a currency is permitted for a given customs clearance type.
     *
     * @param string $customsType
     * @param string $currency
     *
     * @return bool
     */
    public function isCurrencyAllowed(string $customsType, string $currency): bool
    {
        $allowed = $this->getCurrenciesForType($customsType);
        return in_array($currency, $allowed, true);
    }

    /**
     * Get options array for a specific customs clearance type filtered from Magento locale list.
     *
     * @param string $customsType
     *
     * @return array
     */
    public function getOptionArrayForType(string $customsType): array
    {
        $allowed = $this->getCurrenciesForType($customsType);
        return $this->filterCurrencies($allowed);
    }

    /**
     * Filter Magento locale currency list by given allowed currency codes.
     *
     * @param array $allowedCodes
     *
     * @return array
     */
    private function filterCurrencies(array $allowedCodes): array
    {
        $allCurrencies = $this->localeLists->getOptionAllCurrencies();
        $indexed = [];

        foreach ($allCurrencies as $currencyOption) {
            if (!empty($currencyOption['value'])) {
                $indexed[$currencyOption['value']] = $currencyOption;
            }
        }

        $options = [];
        foreach ($allowedCodes as $code) {
            if (isset($indexed[$code])) {
                $options[] = $indexed[$code];
            } else {
                $options[] = [
                    'value' => $code,
                    'label' => $code,
                ];
            }
        }

        return $options;
    }
}
