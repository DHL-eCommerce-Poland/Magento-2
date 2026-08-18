<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LabelType implements OptionSourceInterface
{
    public const BLP = 'BLP';
    public const LBLP = 'LBLP';
    public const ZBLP = 'ZBLP';
    public const ZBLP300 = 'ZBLP300';

    /**
     * Return Label Type option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BLP, 'label' => __('BLP 10x15 PDF Label')],
            ['value' => self::LBLP, 'label' => __('LBLP A4 PDF Label')],
            ['value' => self::ZBLP, 'label' => __('ZBLP Zebra Printer Format')],
            ['value' => self::ZBLP300, 'label' => __('ZBLP 300dpi Zebra Printer Format')],
        ];
    }
}
