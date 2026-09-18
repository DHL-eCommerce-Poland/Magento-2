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
    public const METHOD_PARCEL_POLSKA_COD = 'parcel_polska_cod';
    public const METHOD_PREMIUM = 'premium';
    public const METHOD_PREMIUM_COD = 'premium_cod';
    public const METHOD_PARCEL_9 = 'parcel_9';
    public const METHOD_PARCEL_9_COD = 'parcel_9_cod';
    public const METHOD_PARCEL_12 = 'parcel_12';
    public const METHOD_PARCEL_12_COD = 'parcel_12_cod';
    public const METHOD_ECONOMY = 'economy';
    public const METHOD_ECONOMY_COD = 'economy_cod';
    public const METHOD_MAX_PALETA = 'max_paleta';
    public const METHOD_MAX_PALETA_COD = 'max_paleta_cod';
    public const METHOD_CONNECT = 'connect';
    public const METHOD_CONNECT_COD = 'connect_cod';
    public const METHOD_CONNECT_PARCELSHOP = 'connect_parcelshop';
    public const METHOD_CONNECT_PARCELSHOP_COD = 'connect_parcelshop_cod';
    public const METHOD_CONNECT_PACKSTATION = 'connect_packstation';
    public const METHOD_CONNECT_PACKSTATION_COD = 'connect_packstation_cod';
    public const METHOD_CONNECT_PLUS = 'connect_plus';
    public const METHOD_CONNECT_PLUS_COD = 'connect_plus_cod';
    public const METHOD_CONNECT_PALLET = 'connect_pallet';
    public const METHOD_CONNECT_PALLET_COD = 'connect_pallet_cod';

    /**
     * Map of shipping method codes to DHL API service codes.
     *
     * @var array
     */
    private const METHOD_SERVICE_MAP = [
        self::METHOD_PARCEL_POLSKA => self::SERVICE_AH,
        self::METHOD_PARCEL_POLSKA_COD => self::SERVICE_AH,
        self::METHOD_MAX_PALETA => self::SERVICE_AH,
        self::METHOD_MAX_PALETA_COD => self::SERVICE_AH,
        self::METHOD_PREMIUM => self::SERVICE_PR,
        self::METHOD_PREMIUM_COD => self::SERVICE_PR,
        self::METHOD_PARCEL_9 => self::SERVICE_09,
        self::METHOD_PARCEL_9_COD => self::SERVICE_09,
        self::METHOD_PARCEL_12 => self::SERVICE_12,
        self::METHOD_PARCEL_12_COD => self::SERVICE_12,
        self::METHOD_ECONOMY => self::SERVICE_SP,
        self::METHOD_ECONOMY_COD => self::SERVICE_SP,
        self::METHOD_CONNECT => self::SERVICE_EK,
        self::METHOD_CONNECT_COD => self::SERVICE_EK,
        self::METHOD_CONNECT_PARCELSHOP => self::SERVICE_EK,
        self::METHOD_CONNECT_PARCELSHOP_COD => self::SERVICE_EK,
        self::METHOD_CONNECT_PACKSTATION => self::SERVICE_EK,
        self::METHOD_CONNECT_PACKSTATION_COD => self::SERVICE_EK,
        self::METHOD_CONNECT_PLUS => self::SERVICE_CP,
        self::METHOD_CONNECT_PLUS_COD => self::SERVICE_CP,
        self::METHOD_CONNECT_PALLET => self::SERVICE_CM,
        self::METHOD_CONNECT_PALLET_COD => self::SERVICE_CM,
    ];

    /**
     * Return the supported DHL Parcel services.
     *
     * @return array[]
     */
    public function getMethods(): array
    {
        return [
            [
                'code' => self::METHOD_PARCEL_POLSKA,
                'title' => __('DHL Parcel Polska'),
                'service_code' => self::SERVICE_AH,
            ],
            [
                'code' => self::METHOD_PARCEL_POLSKA_COD,
                'title' => __('DHL Parcel Polska - Cash on Delivery'),
                'service_code' => self::SERVICE_AH,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_PREMIUM,
                'title' => __('DHL Parcel Premium'),
                'service_code' => self::SERVICE_PR,
            ],
            [
                'code' => self::METHOD_PREMIUM_COD,
                'title' => __('DHL Parcel Premium - Cash on Delivery'),
                'service_code' => self::SERVICE_PR,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_PARCEL_9,
                'title' => __('DHL Parcel 9'),
                'service_code' => self::SERVICE_09,
            ],
            [
                'code' => self::METHOD_PARCEL_9_COD,
                'title' => __('DHL Parcel 9 - Cash on Delivery'),
                'service_code' => self::SERVICE_09,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_PARCEL_12,
                'title' => __('DHL Parcel 12'),
                'service_code' => self::SERVICE_12,
            ],
            [
                'code' => self::METHOD_PARCEL_12_COD,
                'title' => __('DHL Parcel 12 - Cash on Delivery'),
                'service_code' => self::SERVICE_12,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_ECONOMY,
                'title' => __('DHL Parcel Economy'),
                'service_code' => self::SERVICE_SP,
            ],
            [
                'code' => self::METHOD_ECONOMY_COD,
                'title' => __('DHL Parcel Economy - Cash on Delivery'),
                'service_code' => self::SERVICE_SP,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_MAX_PALETA,
                'title' => __('DHL Parcel Max Paleta'),
                'service_code' => self::SERVICE_AH,
            ],
            [
                'code' => self::METHOD_MAX_PALETA_COD,
                'title' => __('DHL Parcel Max Paleta - Cash on Delivery'),
                'service_code' => self::SERVICE_AH,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT,
                'title' => __('DHL Parcel Connect'),
                'service_code' => self::SERVICE_EK,
            ],
            [
                'code' => self::METHOD_CONNECT_COD,
                'title' => __('DHL Parcel Connect - Cash on Delivery'),
                'service_code' => self::SERVICE_EK,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PARCELSHOP,
                'title' => __('DHL Parcel Connect Parcelshop'),
                'service_code' => self::SERVICE_EK,
            ],
            [
                'code' => self::METHOD_CONNECT_PARCELSHOP_COD,
                'title' => __('DHL Parcel Connect Parcelshop - Cash on Delivery'),
                'service_code' => self::SERVICE_EK,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PACKSTATION,
                'title' => __('DHL Parcel Connect Packstation'),
                'service_code' => self::SERVICE_EK,
            ],
            [
                'code' => self::METHOD_CONNECT_PACKSTATION_COD,
                'title' => __('DHL Parcel Connect Packstation - Cash on Delivery'),
                'service_code' => self::SERVICE_EK,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PLUS,
                'title' => __('DHL Parcel Connect Plus'),
                'service_code' => self::SERVICE_CP,
            ],
            [
                'code' => self::METHOD_CONNECT_PLUS_COD,
                'title' => __('DHL Parcel Connect Plus - Cash on Delivery'),
                'service_code' => self::SERVICE_CP,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PALLET,
                'title' => __('DHL Parcel Connect Pallet - Paleta'),
                'service_code' => self::SERVICE_CM,
            ],
            [
                'code' => self::METHOD_CONNECT_PALLET_COD,
                'title' => __('DHL Parcel Connect Pallet - Paleta - Cash on Delivery'),
                'service_code' => self::SERVICE_CM,
                'is_cod' => true,
            ],
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

    /**
     * Check if a shipping method code represents a Cash On Delivery (COD) method.
     *
     * @param string|null $shippingMethod
     *
     * @return bool
     */
    public function isCodMethod(?string $shippingMethod): bool
    {
        if ($shippingMethod === null || trim($shippingMethod) === '') {
            return false;
        }

        $code = strtolower(trim($shippingMethod));
        if (str_starts_with($code, Carrier::CODE_PREFIX)) {
            $code = substr($code, strlen(Carrier::CODE_PREFIX));
        }

        return str_ends_with($code, '_cod');
    }
}
