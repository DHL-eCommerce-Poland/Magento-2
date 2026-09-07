<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\ViewModel\Adminhtml\Shipment;

use DateTime;
use Ideo\Dhl\ViewModel\Adminhtml\Shipment\DatePickerConfig;
use IntlDateFormatter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatePickerConfigTest extends TestCase
{
    /**
     * @var TimezoneInterface|MockObject
     */
    private $localeDateMock;

    /**
     * @var DatePickerConfig
     */
    private $viewModel;

    protected function setUp(): void
    {
        $this->localeDateMock = $this->createMock(TimezoneInterface::class);
        $this->viewModel = new DatePickerConfig($this->localeDateMock);
    }

    public function testGetDateFormatReturnsShortFormatFromLocaleDate(): void
    {
        $this->localeDateMock->expects($this->once())
            ->method('getDateFormat')
            ->with(IntlDateFormatter::SHORT)
            ->willReturn('M/d/yy');

        $this->assertEquals('M/d/yy', $this->viewModel->getDateFormat());
    }

    public function testGetFormattedDateFormatsDateUsingLocaleDate(): void
    {
        $testDate = new DateTime('2026-09-02');

        $this->localeDateMock->expects($this->once())
            ->method('formatDate')
            ->with($testDate, IntlDateFormatter::SHORT, false)
            ->willReturn('09/2/2026');

        $this->assertEquals('09/2/2026', $this->viewModel->getFormattedDate($testDate));
    }

    public function testGetFormattedDateWithNullPassesNullToLocaleDate(): void
    {
        $this->localeDateMock->expects($this->once())
            ->method('formatDate')
            ->with(null, IntlDateFormatter::SHORT, false)
            ->willReturn('09/2/2026');

        $this->assertEquals('09/2/2026', $this->viewModel->getFormattedDate());
    }
}
