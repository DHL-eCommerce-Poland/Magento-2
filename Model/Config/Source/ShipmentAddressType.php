<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ShipmentAddressType implements OptionSourceInterface
{
    /**
     * Return option array for Shipment Recipient Address Type (Private vs Company).
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'C', 'label' => __('Private Address (C)')],
            ['value' => 'B', 'label' => __('Company Address (B)')],
        ];
    }
}
