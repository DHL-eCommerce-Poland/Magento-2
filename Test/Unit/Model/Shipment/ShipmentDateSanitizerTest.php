<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use DateTime;
use Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ShipmentDateSanitizerTest extends TestCase
{
    /**
     * @var Stub
     */
    private $timezoneStub;
    /**
     * @var ShipmentDateSanitizer
     */
    private $sanitizer;

    protected function setUp(): void
    {
        $this->timezoneStub = $this->createStub(TimezoneInterface::class);
        $this->timezoneStub->method('scopeDate')
            ->willReturn(new DateTime('2026-09-07 00:00:00'));

        $this->sanitizer = new ShipmentDateSanitizer($this->timezoneStub);
    }

    public function testSanitizeEmptyReturnsToday(): void
    {
        $this->assertEquals('2026-09-07', $this->sanitizer->sanitize(''));
        $this->assertEquals('2026-09-07', $this->sanitizer->sanitize(null));
    }

    public function testSanitizeValidIsoDate(): void
    {
        $this->assertEquals('2026-09-10', $this->sanitizer->sanitize('2026-09-10'));
    }

    public function testSanitizeValidPolishDate(): void
    {
        $this->assertEquals('2026-09-10', $this->sanitizer->sanitize('10.09.2026'));
    }

    public function testSanitizeValidUsDate(): void
    {
        $this->assertEquals('2026-09-10', $this->sanitizer->sanitize('09/10/2026'));
    }

    public function testSanitizeCorruptedYearReturnsToday(): void
    {
        $this->assertEquals('2026-09-07', $this->sanitizer->sanitize('2171-10-09'));
        $this->assertEquals('2026-09-07', $this->sanitizer->sanitize('09.10.2171'));
    }

    public function testSanitizePastDateReturnsToday(): void
    {
        $this->assertEquals('2026-09-07', $this->sanitizer->sanitize('2020-01-01'));
    }

    public function testSanitizeInvalidDateReturnsToday(): void
    {
        $this->assertEquals('2026-09-07', $this->sanitizer->sanitize('invalid-date'));
    }
}
