<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ReturnBookCourier implements OptionSourceInterface
{
    public const DROP_OFF_OR_COURIER = '0';
    public const DROP_OFF = '1';

    /**
     * Return option array for Return Book Courier method.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::DROP_OFF_OR_COURIER, 'label' => __('Drop-Off Point or Courier')],
            ['value' => self::DROP_OFF, 'label' => __('Drop-Off Point')],
        ];
    }
}
