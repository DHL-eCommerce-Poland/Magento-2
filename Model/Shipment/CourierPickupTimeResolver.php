<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Exception;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Throwable;

class CourierPickupTimeResolver
{
    /**
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Resolve available courier pickup hours for sender postal code and date.
     *
     * @param string $postalCode
     * @param string|null $requestedDate YYYY-MM-DD format (defaults to today)
     * @param string $environment ('sandbox' or 'production')
     * @param string|null $currentTime HH:MM format for test injection (defaults to current time)
     *
     * @return array
     * @throws Exception
     */
    public function resolveAvailableHours(
        string $postalCode,
        ?string $requestedDate = null,
        string $environment = 'sandbox',
        ?string $currentTime = null
    ): array {
        $today = $this->timezone->scopeDate()->format('Y-m-d');
        $formattedDate = $today;

        $rawDate = trim((string) $requestedDate);
        if (!empty($rawDate)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
                $formattedDate = $rawDate;
            } elseif (preg_match('/^(\d{1,2})[\.\/](\d{1,2})[\.\/](\d{4})$/', $rawDate, $m)) {
                $d = (int)$m[1];
                $mNum = (int)$m[2];
                $y = (int)$m[3];
                if (str_contains($rawDate, '.') || $d > 12) {
                    $formattedDate = sprintf('%04d-%02d-%02d', $y, $mNum, $d);
                } else {
                    $formattedDate = sprintf('%04d-%02d-%02d', $y, $d, $mNum);
                }
            } else {
                try {
                    $formattedDate = $this->timezone->date($rawDate)->format('Y-m-d');
                } catch (Throwable) {
                    $formattedDate = $today;
                }
            }
        }

        $year = (int) substr($formattedDate, 0, 4);
        if ($year < 2024 || $year > 2050) {
            $formattedDate = $today;
        }

        $currentTime = $currentTime ?: $this->timezone->scopeDate()->format('H:i');

        $requestedDt = $this->timezone->date($formattedDate);
        if ((int) $requestedDt->format('N') >= 6) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('Courier pickup is not available for the selected date. Please select another date in the calendar.'),
                'available_hours' => [],
                'dr_pickup_from' => null,
                'dr_pickup_to' => null,
            ];
        }

        $credentials = $this->credentialsProvider->getCredentials($environment);
        $result = $this->client->getPostalCodeServices($credentials, $postalCode, $formattedDate);

        $isTodayRequested = ($formattedDate === $today);

        if (!$result->isSuccess()) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('Courier pickup is not available for the selected date. Please select another date in the calendar.'),
                'available_hours' => [],
                'dr_pickup_from' => null,
                'dr_pickup_to' => null,
                'error' => $result->getErrorMessage() ?: 'DHL API error',
            ];
        }

        $drFrom = $result->getDrPickupFrom() ?: $result->getExPickupFrom();
        $drTo = $result->getDrPickupTo() ?: $result->getExPickupTo();

        if (empty($drFrom) || empty($drTo) || $drFrom === 'brak' || $drTo === 'brak') {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('Courier pickup is not available for the selected date. Please select another date in the calendar.'),
                'available_hours' => [],
                'dr_pickup_from' => null,
                'dr_pickup_to' => null,
            ];
        }

        // If checking for today, verify current time has not passed drPickupTo
        if ($isTodayRequested && $currentTime >= $drTo) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('Courier pickup is not available for the selected date. Please select another date in the calendar.'),
                'available_hours' => [],
                'dr_pickup_from' => $drFrom,
                'dr_pickup_to' => $drTo,
            ];
        }

        $availableHours = $this->generateHourSlots($drFrom, $drTo, $isTodayRequested ? $currentTime : null);

        if (empty($availableHours)) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('Courier pickup is not available for the selected date. Please select another date in the calendar.'),
                'available_hours' => [],
                'dr_pickup_from' => $drFrom,
                'dr_pickup_to' => $drTo,
            ];
        }

        return [
            'is_today_possible' => true,
            'is_date_available' => true,
            'message' => null,
            'available_hours' => $availableHours,
            'dr_pickup_from' => $drFrom,
            'dr_pickup_to' => $drTo,
        ];
    }

    /**
     * Resolve available courier pickup hours for multiple postal codes (e.g. mass action).
     * Calculates the intersection window (MAX of pickupFrom, MIN of pickupTo) across all postal codes.
     *
     * @param string[] $postalCodes
     * @param string|null $requestedDate
     * @param string $environment
     * @param string|null $currentTime
     *
     * @return array
     * @throws Exception
     */
    public function resolveAvailableHoursForPostalCodes(
        array $postalCodes,
        ?string $requestedDate = null,
        string $environment = 'sandbox',
        ?string $currentTime = null
    ): array {
        $postalCodes = array_values(array_unique(array_filter($postalCodes)));

        if (empty($postalCodes)) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('Sender postal code is required.'),
                'available_hours' => [],
                'dr_pickup_from' => null,
                'dr_pickup_to' => null,
            ];
        }

        if (count($postalCodes) === 1) {
            return $this->resolveAvailableHours($postalCodes[0], $requestedDate, $environment, $currentTime);
        }

        $allFrom = [];
        $allTo = [];

        foreach ($postalCodes as $code) {
            $res = $this->resolveAvailableHours($code, $requestedDate, $environment, $currentTime);
            if (!$res['is_today_possible'] || empty($res['dr_pickup_from']) || empty($res['dr_pickup_to'])) {
                return [
                    'is_today_possible' => false,
                    'is_date_available' => false,
                    'message' => (string) __('Courier pickup is not available for the selected date. Please select another date in the calendar.'),
                    'available_hours' => [],
                    'dr_pickup_from' => null,
                    'dr_pickup_to' => null,
                ];
            }
            $allFrom[] = $res['dr_pickup_from'];
            $allTo[] = $res['dr_pickup_to'];
        }

        // Common window: latest pickupFrom, earliest pickupTo
        $maxFrom = max($allFrom);
        $minTo = min($allTo);

        if ($maxFrom >= $minTo) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('Courier pickup is not available for the selected date. Please select another date in the calendar.'),
                'available_hours' => [],
                'dr_pickup_from' => null,
                'dr_pickup_to' => null,
            ];
        }

        $availableHours = [
            [
                'from' => $maxFrom,
                'to' => $minTo,
                'label' => sprintf('%s - %s', $maxFrom, $minTo),
            ],
        ];

        return [
            'is_today_possible' => true,
            'is_date_available' => true,
            'message' => null,
            'available_hours' => $availableHours,
            'dr_pickup_from' => $maxFrom,
            'dr_pickup_to' => $minTo,
        ];
    }

    /**
     * Generate available hour range slots between drFrom and drTo.
     *
     * @param string $drFrom e.g. "11:35"
     * @param string $drTo e.g. "17:30"
     * @param string|null $currentTime e.g. "11:30"
     *
     * @return array Array of slots [['from' => '11:35', 'to' => '17:30', 'label' => '11:35 - 17:30']]
     */
    private function generateHourSlots(string $drFrom, string $drTo, ?string $currentTime = null): array
    {
        $slots = [];

        // Use exact time bounds returned by DHL WebAPI2
        $slots[] = [
            'from' => $drFrom,
            'to' => $drTo,
            'label' => sprintf('%s - %s', $drFrom, $drTo),
        ];

        return $slots;
    }
}
