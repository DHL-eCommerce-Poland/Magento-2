<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

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

        // ISO format YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $m)) {
            $y = (int)$m[1];
            $mNum = (int)$m[2];
            $d = (int)$m[3];
            if (checkdate($mNum, $d, $y)) {
                $formatted = sprintf('%04d-%02d-%02d', $y, $mNum, $d);
                if ($y < $minYear || $y > $maxYear || (!$allowPastDate && $formatted < $today)) {
                    return $fallback;
                }
                return $formatted;
            }
        }

        // Polish / European format DD.MM.YYYY
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $rawDate, $m)) {
            $d = (int)$m[1];
            $mNum = (int)$m[2];
            $y = (int)$m[3];
            if (checkdate($mNum, $d, $y)) {
                $formatted = sprintf('%04d-%02d-%02d', $y, $mNum, $d);
                if ($y < $minYear || $y > $maxYear || (!$allowPastDate && $formatted < $today)) {
                    return $fallback;
                }
                return $formatted;
            }
        }

        // US format MM/DD/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $rawDate, $m)) {
            $mNum = (int)$m[1];
            $d = (int)$m[2];
            $y = (int)$m[3];
            if (checkdate($mNum, $d, $y)) {
                $formatted = sprintf('%04d-%02d-%02d', $y, $mNum, $d);
                if ($y < $minYear || $y > $maxYear || (!$allowPastDate && $formatted < $today)) {
                    return $fallback;
                }
                return $formatted;
            }
        }

        return $fallback;
    }
}
