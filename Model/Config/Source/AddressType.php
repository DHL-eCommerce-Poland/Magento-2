<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AddressType implements OptionSourceInterface
{
    /**
     * Return option array for Address Type.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'N', 'label' => __('Based on Cart')],
            ['value' => 'C', 'label' => __('Private Address')],
            ['value' => 'B', 'label' => __('Company Address')],
        ];
    }
}
