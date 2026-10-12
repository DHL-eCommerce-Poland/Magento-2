<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

class StreetAddressParser
{
    /**
     * Parse single or multiple street address lines.
     *
     * @param string|array $streetInput
     *
     * @return array
     */
    public function parse(string|array $streetInput): array
    {
        $lines = is_array($streetInput) ? $streetInput : explode("\n", $streetInput);
        $lines = array_values(array_filter(array_map('trim', $lines)));

        if (empty($lines)) {
            return ['street' => '', 'houseNumber' => '', 'flatNumber' => ''];
        }

        $fullAddress = implode(' ', $lines);

        // Standard pattern: "Street Name 12/34" or "Street Name 12 m. 34" or "Street 12a/3"
        // Pattern 1: Street with slash separation e.g. "Warszawska 29/44", "Marszałkowska 10/12" or "1 Maja 5/2"
        if (preg_match('/^(.*?)\s+([0-9]+\s*[a-zA-Z]?\s*[\/\|\-]\s*[0-9a-zA-Z]+)$/u', $fullAddress, $matches)) {
            $houseNum = (string) preg_replace('/\s*([\/\-\|])\s*/', '$1', trim($matches[2]));
            return [
                'street' => trim($matches[1]),
                'houseNumber' => $houseNum,
                'flatNumber' => '',
            ];
        }

        // Pattern 2: Explicit "m.", "m", "lok.", "lok" flat indicator e.g. "ul. 1 Maja 5 m. 2" or "Polna 12 lok. 4"
        $flatPattern = '/^(.*?)\s+([0-9]+\s*[a-zA-Z]?)\s+(?:m\.?|lok\.?|lokal)\s*([0-9a-zA-Z]+)$/ui';
        if (preg_match($flatPattern, $fullAddress, $matches)) {
            return [
                'street' => trim($matches[1]),
                'houseNumber' => trim($matches[2]),
                'flatNumber' => trim($matches[3]),
            ];
        }

        // Pattern 3: Street + house number without flat number e.g. "Krakowskie Przedmieście 14a" or "1 Maja 5"
        if (preg_match('/^(.*?)\s+([0-9]+\s*[a-zA-Z]?)$/u', $fullAddress, $matches)) {
            return [
                'street' => trim($matches[1]),
                'houseNumber' => trim($matches[2]),
                'flatNumber' => '',
            ];
        }

        // Pattern 4: House number prefix e.g. "30 Rue des Buissons", "10A Rue des Buissons",
        // "10C/23 Rue des Buissons", "30 bis Rue des Buissons", "10-12 Rue de la Paix"
        $prefixPattern = '/^([0-9]+\s*(?:bis|ter|quater|[a-zA-Z])?(?:\s*[\/\-\|]\s*[0-9a-zA-Z]+)?)\s*[,.]?\s+(.+)$/ui';
        if (preg_match($prefixPattern, $fullAddress, $matches)) {
            $houseNum = (string) preg_replace('/\s*([\/\-\|])\s*/', '$1', trim($matches[1]));
            return [
                'street' => trim($matches[2]),
                'houseNumber' => $houseNum,
                'flatNumber' => '',
            ];
        }

        // Fallback if 2 lines: Line 1 = Street, Line 2 = House/Flat
        if (count($lines) >= 2) {
            $street = $lines[0];
            $secondLine = $lines[1];
            if (preg_match('/^([0-9]+\s*[a-zA-Z]?\s*[\/\|\-]\s*[0-9a-zA-Z]+)$/u', $secondLine, $matches)) {
                $houseNum = (string) preg_replace('/\s*([\/\-\|])\s*/', '$1', trim($matches[1]));
                return [
                    'street' => $street,
                    'houseNumber' => $houseNum,
                    'flatNumber' => '',
                ];
            }
            return [
                'street' => $street,
                'houseNumber' => $secondLine,
                'flatNumber' => '',
            ];
        }

        return [
            'street' => $fullAddress,
            'houseNumber' => '',
            'flatNumber' => '',
        ];
    }
}
