<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

class PostalCodeSanitizer
{
    /**
     * Remove spaces from postal codes and hyphens from Polish postal codes for DHL.
     *
     * @param string $postalCode
     * @param string $countryCode
     *
     * @return string
     */
    public function sanitize(string $postalCode, string $countryCode = 'PL'): string
    {
        $postalCode = str_replace(' ', '', trim($postalCode));
        if (strtoupper($countryCode) === 'PL') {
            return str_replace('-', '', $postalCode);
        }

        return $postalCode;
    }
}
