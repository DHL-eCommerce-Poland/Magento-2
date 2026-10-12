<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class ShipmentDateSanitizer
{
    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Sanitize and validate shipment date string, returning YYYY-MM-DD.
     *
     * @param string|null $rawDate
     * @param string|null $fallbackDate Format YYYY-MM-DD (defaults to today)
     * @param bool $allowPastDate Whether past dates are allowed (default false)
     *
     * @return string Format YYYY-MM-DD
     */
    public function sanitize(?string $rawDate, ?string $fallbackDate = null, bool $allowPastDate = false): string
    {
        $scopeDate = $this->timezone->scopeDate();
        $today = $scopeDate ? $scopeDate->format('Y-m-d') : date('Y-m-d');
        $fallback = $fallbackDate ?: $today;
        $rawDate = trim((string)$rawDate);
        if ($rawDate === '') {
            return $fallback;
        }

        $currentYear = (int)substr($today, 0, 4);
        $minYear = 2024;
        $maxYear = $currentYear + 5;

        $parts = $this->parseDateParts($rawDate);
        if ($parts === null) {
            return $fallback;
        }

        [$y, $mNum, $d] = $parts;
        if ($y < 100) {
            $y += 2000;
        }

        if (!checkdate($mNum, $d, $y)) {
            return $fallback;
        }

        $formatted = sprintf('%04d-%02d-%02d', $y, $mNum, $d);
        if ($y < $minYear || $y > $maxYear || (!$allowPastDate && $formatted < $today)) {
            return $fallback;
        }

        return $formatted;
    }

    /**
     * Parse raw date string into [year, month, day] tuple.
     *
     * @param string $date
     *
     * @return int[]|null
     */
    private function parseDateParts(string $date): ?array
    {
        // 1. Year first format: YYYY-MM-DD, YYYY/MM/DD, YYYY.MM.DD
        if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})$/', $date, $m)) {
            return [(int)$m[1], (int)$m[2], (int)$m[3]];
        }

        // 2. US format (slash separated): MM/DD/YYYY or MM/DD/YY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date, $m)) {
            return [(int)$m[3], (int)$m[1], (int)$m[2]];
        }

        // 3. European format (dot or hyphen separated): DD.MM.YYYY, DD-MM-YYYY, DD.MM.YY, DD-MM-YY
        if (preg_match('/^(\d{1,2})[\.-](\d{1,2})[\.-](\d{2,4})$/', $date, $m)) {
            return [(int)$m[3], (int)$m[2], (int)$m[1]];
        }

        return null;
    }
}
