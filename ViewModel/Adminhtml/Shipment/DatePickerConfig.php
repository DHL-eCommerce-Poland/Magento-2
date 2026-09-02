<?php

declare(strict_types=1);

namespace Ideo\Dhl\ViewModel\Adminhtml\Shipment;

use DateTimeInterface;
use IntlDateFormatter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class DatePickerConfig implements ArgumentInterface
{
    /**
     * @param TimezoneInterface $localeDate
     */
    public function __construct(
        private readonly TimezoneInterface $localeDate
    ) {
    }

    /**
     * Get date format string for active locale (ICU pattern e.g. M/d/yy or dd.MM.yyyy).
     *
     * @return string
     */
    public function getDateFormat(): string
    {
        return $this->localeDate->getDateFormat(IntlDateFormatter::SHORT);
    }

    /**
     * Format given DateTime object (or current store date/time if null) according to active locale short date format.
     *
     * @param DateTimeInterface|null $date
     *
     * @return string
     */
    public function getFormattedDate(?DateTimeInterface $date = null): string
    {
        return $this->localeDate->formatDate(
            $date,
            IntlDateFormatter::SHORT,
            false
        );
    }
}
