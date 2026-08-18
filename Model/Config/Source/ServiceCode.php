<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ServiceCode implements OptionSourceInterface
{
    /**
     * Return DHL service code option array for Service dropdown.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- Please Select --')],
            ['value' => 'AH', 'label' => __('AH - Domestic Shipment')],
            ['value' => '09', 'label' => __('09 - Delivery by 9:00 AM')],
            ['value' => '12', 'label' => __('12 - Delivery by 12:00 PM')],
            ['value' => 'PR', 'label' => __('PR - Premium Shipment')],
            ['value' => 'SP', 'label' => __('SP - Domestic Service Point Delivery')],
            ['value' => 'EK', 'label' => __('EK - Connect Shipment')],
            ['value' => 'EKSP', 'label' => __('EKSP - International Service Point Delivery')],
            ['value' => 'CP', 'label' => __('CP - Connect Plus Shipment')],
            ['value' => 'CM', 'label' => __('CM - Connect Plus Pallet Shipment')],
        ];
    }
}
