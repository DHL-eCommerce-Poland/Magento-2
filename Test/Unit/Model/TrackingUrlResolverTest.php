<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model;

use DhlPoland\Dhl\Model\TrackingUrlResolver;
use PHPUnit\Framework\TestCase;

class TrackingUrlResolverTest extends TestCase
{
    /**
     * Test that DHL public tracking URL is constructed correctly for a given tracking number.
     */
    public function testGetUrlBuildsCorrectDhlTrackingUrl(): void
    {
        $resolver = new TrackingUrlResolver();
        $url = $resolver->getUrl(' 1234567890 ');

        self::assertSame('https://mojdhl.pl/sprawdz?paczki=90039690961', $resolver->getUrl(' 90039690961 '));
    }
}
