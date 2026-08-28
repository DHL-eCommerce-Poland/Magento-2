<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;

class CourierPickupTimeResolver
{
    /**
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider
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
     */
    public function resolveAvailableHours(
        string $postalCode,
        ?string $requestedDate = null,
        string $environment = 'sandbox',
        ?string $currentTime = null
    ): array {
        $today = date('Y-m-d');
        $requestedDate = $requestedDate ?: $today;
        $currentTime = $currentTime ?: date('H:i');

        $credentials = $this->credentialsProvider->getCredentials($environment);
        $result = $this->client->getPostalCodeServices($credentials, $postalCode, $requestedDate);

        $isTodayRequested = ($requestedDate === $today);

        if (!$result->isSuccess()) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'),
                'available_hours' => [],
                'dr_pickup_from' => null,
                'dr_pickup_to' => null,
                'error' => $result->getErrorMessage() ?: 'DHL API error',
            ];
        }

        $drFrom = $result->getDrPickupFrom() ?: $result->getExPickupFrom();
        $drTo = $result->getDrPickupTo() ?: $result->getExPickupTo();

        if (empty($drFrom) || empty($drTo)) {
            return [
                'is_today_possible' => false,
                'is_date_available' => false,
                'message' => (string) __('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'),
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
                'message' => (string) __('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'),
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
                'message' => (string) __('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'),
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
     */
    public function resolveAvailableHoursForPostalCodes(
        array $postalCodes,
        ?string $requestedDate = null,
        string $environment = 'sandbox',
        ?string $currentTime = null
    ): array {
        $postalCodes = array_values(array_unique(array_filter($postalCodes)));

        if (empty($postalCodes)) {
            return $this->resolveAvailableHours('00-001', $requestedDate, $environment, $currentTime);
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
                    'message' => (string) __('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'),
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
                'message' => (string) __('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'),
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
