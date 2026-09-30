<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Ideo\Dhl\Model\Api\GetLabelsResult;
use Magento\Framework\Data\OptionSourceInterface;

class LabelType implements OptionSourceInterface
{
    /**
     * Return Label Type option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => GetLabelsResult::LABEL_TYPE_BLP, 'label' => __('BLP 10x15 PDF Label')],
            ['value' => GetLabelsResult::LABEL_TYPE_LBLP, 'label' => __('LBLP A4 PDF Label')],
            ['value' => GetLabelsResult::LABEL_TYPE_ZBLP, 'label' => __('ZBLP Zebra Printer Format')],
            ['value' => GetLabelsResult::LABEL_TYPE_ZBLP300, 'label' => __('ZBLP 300dpi Zebra Printer Format')],
        ];
    }
}
