<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ReturnQrCodeType implements OptionSourceInterface
{
    public const QR_PDF = 'QR_PDF';
    public const QR2_IMG = 'QR2_IMG';
    public const QR4_IMG = 'QR4_IMG';
    public const QR6_IMG = 'QR6_IMG';

    /**
     * Return option array for Return QR Code Type.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => ''],
            ['value' => self::QR_PDF, 'label' => __('QR_PDF - PDF QR Code for ZK and ZC Shipment')],
            ['value' => self::QR2_IMG, 'label' => __('QR2_IMG - PNG QR Code (2cm x 2cm) for ZK and ZC Shipment')],
            ['value' => self::QR4_IMG, 'label' => __('QR4_IMG - PNG QR Code (4cm x 4cm) for ZK and ZC Shipment')],
            ['value' => self::QR6_IMG, 'label' => __('QR6_IMG - PNG QR Code (6cm x 6cm) for ZK and ZC Shipment')],
        ];
    }
}
