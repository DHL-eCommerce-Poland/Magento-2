<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

class TrackingUrlResolver
{
    private const BASE_URL = 'https://mojdhl.pl/sprawdz?paczki=';

    /**
     * Get public DHL tracking URL for a given tracking number.
     *
     * @param string $trackingNumber
     *
     * @return string
     */
    public function getUrl(string $trackingNumber): string
    {
        return self::BASE_URL . urlencode(trim($trackingNumber));
    }
}
