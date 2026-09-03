<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ShipmentAddressType implements OptionSourceInterface
{
    public const TYPE_CONSUMER = AddressType::TYPE_CONSUMER;
    public const TYPE_BUSINESS = AddressType::TYPE_BUSINESS;

    /**
     * Return option array for Shipment Recipient Address Type (Private vs Company).
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_CONSUMER, 'label' => __('Private Address (C)')],
            ['value' => self::TYPE_BUSINESS, 'label' => __('Company Address (B)')],
        ];
    }
}
