<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Ideo\Dhl\Model\MethodCatalog;
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
            ['value' => MethodCatalog::SERVICE_AH, 'label' => __('AH - Domestic Shipment')],
            ['value' => MethodCatalog::SERVICE_09, 'label' => __('09 - Delivery by 9:00 AM')],
            ['value' => MethodCatalog::SERVICE_12, 'label' => __('12 - Delivery by 12:00 PM')],
            ['value' => MethodCatalog::SERVICE_PR, 'label' => __('PR - Premium Shipment')],
            ['value' => MethodCatalog::SERVICE_SP, 'label' => __('SP - Domestic Service Point Delivery')],
            ['value' => MethodCatalog::SERVICE_EK, 'label' => __('EK - Connect Shipment')],
            ['value' => MethodCatalog::SERVICE_CP, 'label' => __('CP - Connect Plus Shipment')],
            ['value' => MethodCatalog::SERVICE_CM, 'label' => __('CM - Connect Plus Pallet Shipment')],
        ];
    }
}
