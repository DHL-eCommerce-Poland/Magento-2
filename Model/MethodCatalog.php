<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

class MethodCatalog
{
    public const SERVICE_AH = 'AH';
    public const SERVICE_PR = 'PR';
    public const SERVICE_09 = '09';
    public const SERVICE_12 = '12';
    public const SERVICE_SP = 'SP';
    public const SERVICE_PS = 'PS';
    public const SERVICE_EK = 'EK';
    public const SERVICE_EKS = 'EKS';
    public const SERVICE_CP = 'CP';
    public const SERVICE_CM = 'CM';
    public const SERVICE_LM = 'LM';

    public const METHOD_PARCEL_POLSKA = 'parcel_polska';
    public const METHOD_PREMIUM = 'premium';
    public const METHOD_PARCEL_9 = 'parcel_9';
    public const METHOD_PARCEL_12 = 'parcel_12';
    public const METHOD_ECONOMY = 'economy';
    public const METHOD_MAX_PALETA = 'max_paleta';
    public const METHOD_CONNECT = 'connect';
    public const METHOD_CONNECT_PARCEL_SHOP = 'connect_parcel_shop';
    public const METHOD_CONNECT_PACKSTATION = 'connect_packstation';
    public const METHOD_CONNECT_PLUS = 'connect_plus';
    public const METHOD_CONNECT_PALLET = 'connect_pallet';

    /**
     * Map of shipping method codes to DHL API service codes.
     *
     * @var array
     */
    private const METHOD_SERVICE_MAP = [
        self::METHOD_PARCEL_POLSKA => self::SERVICE_AH,
        self::METHOD_MAX_PALETA => self::SERVICE_AH,
        self::METHOD_PREMIUM => self::SERVICE_PR,
        self::METHOD_PARCEL_9 => self::SERVICE_09,
        self::METHOD_PARCEL_12 => self::SERVICE_12,
        self::METHOD_ECONOMY => self::SERVICE_SP,
        self::METHOD_CONNECT => self::SERVICE_EK,
        self::METHOD_CONNECT_PARCEL_SHOP => self::SERVICE_EK,
        self::METHOD_CONNECT_PACKSTATION => self::SERVICE_EK,
        self::METHOD_CONNECT_PLUS => self::SERVICE_CP,
        self::METHOD_CONNECT_PALLET => self::SERVICE_CM,
    ];

    /**
     * Return the supported DHL Parcel services.
     *
     * @return array[]
     */
    public function getMethods(): array
    {
        return [
            ['code' => self::METHOD_PARCEL_POLSKA, 'title' => __('DHL Parcel Polska'), 'service_code' => self::SERVICE_AH],
            ['code' => self::METHOD_PREMIUM, 'title' => __('DHL Parcel Premium'), 'service_code' => self::SERVICE_PR],
            ['code' => self::METHOD_PARCEL_9, 'title' => __('DHL Parcel 9'), 'service_code' => self::SERVICE_09],
            ['code' => self::METHOD_PARCEL_12, 'title' => __('DHL Parcel 12'), 'service_code' => self::SERVICE_12],
            ['code' => self::METHOD_ECONOMY, 'title' => __('DHL Parcel Economy'), 'service_code' => self::SERVICE_SP],
            ['code' => self::METHOD_MAX_PALETA, 'title' => __('DHL Parcel Max Paleta'), 'service_code' => self::SERVICE_AH],
            ['code' => self::METHOD_CONNECT, 'title' => __('DHL Parcel Connect'), 'service_code' => self::SERVICE_EK],
            ['code' => self::METHOD_CONNECT_PARCEL_SHOP, 'title' => __('DHL Parcel Connect Parcel Shop'), 'service_code' => self::SERVICE_EK],
            ['code' => self::METHOD_CONNECT_PACKSTATION, 'title' => __('DHL Parcel Connect Packstation'), 'service_code' => self::SERVICE_EK],
            ['code' => self::METHOD_CONNECT_PLUS, 'title' => __('DHL Parcel Connect Plus'), 'service_code' => self::SERVICE_CP],
            ['code' => self::METHOD_CONNECT_PALLET, 'title' => __('DHL Parcel Connect Pallet - Paleta'), 'service_code' => self::SERVICE_CM],
        ];
    }

    /**
     * Resolve DHL API service code from a Magento shipping method code.
     *
     * @param string|null $shippingMethod
     *
     * @return string
     */
    public function getServiceCodeForMethod(?string $shippingMethod): string
    {
        if ($shippingMethod === null || trim($shippingMethod) === '') {
            return '';
        }

        $code = strtolower(trim($shippingMethod));
        if (str_starts_with($code, Carrier::CODE_PREFIX)) {
            $code = substr($code, strlen(Carrier::CODE_PREFIX));
        }

        return self::METHOD_SERVICE_MAP[$code] ?? '';
    }
}
