<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Shipment\PostalCodeSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostalCodeSanitizerTest extends TestCase
{
    /**
     * @var PostalCodeSanitizer
     */
    private $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new PostalCodeSanitizer();
    }

    #[DataProvider('postalCodeDataProvider')]
    public function testSanitize(string $inputCode, string $countryCode, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($inputCode, $countryCode));
    }

    public static function postalCodeDataProvider(): array
    {
        return [
            'PL with hyphen' => ['00-950', 'PL', '00950'],
            'PL without hyphen' => ['00950', 'PL', '00950'],
            'PL lowercase country' => ['00-950', 'pl', '00950'],
            'PL with surrounding spaces' => ['  00-950  ', 'PL', '00950'],
            'PL with internal spaces' => ['00 950', 'PL', '00950'],
            'CZ with internal space' => ['100 00', 'CZ', '10000'],
            'GB alphanumeric with space' => ['SW1A 1AA', 'GB', 'SW1A1AA'],
            'DE with hyphen' => ['D-10115', 'DE', 'D-10115'],
            'US zip extended' => ['90210-1234', 'US', '90210-1234'],
            'Empty string' => ['', 'PL', ''],
        ];
    }
}
