<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model;

use DhlPoland\Dhl\Model\Config\Source\InternationalMapPointType;

class MethodCatalog
{
    public const SERVICE_AH = 'AH';
    public const SERVICE_PR = 'PR';
    public const SERVICE_09 = '09';
    public const SERVICE_12 = '12';
    public const SERVICE_SP = 'SP';
    public const SERVICE_EK = 'EK';
    public const SERVICE_CP = 'CP';
    public const SERVICE_CM = 'CM';

    public const METHOD_PARCEL_POLSKA = 'parcel_polska';
    public const METHOD_PARCEL_POLSKA_COD = 'parcel_polska_cod';
    public const METHOD_PREMIUM = 'premium';
    public const METHOD_PREMIUM_COD = 'premium_cod';
    public const METHOD_PARCEL_9 = 'parcel_9';
    public const METHOD_PARCEL_9_COD = 'parcel_9_cod';
    public const METHOD_PARCEL_12 = 'parcel_12';
    public const METHOD_PARCEL_12_COD = 'parcel_12_cod';
    public const METHOD_ECONOMY_ALL = 'economy_all';
    public const METHOD_ECONOMY_ALL_COD = 'economy_all_cod';
    public const METHOD_ECONOMY_POP = 'economy_pop';
    public const METHOD_ECONOMY_POP_COD = 'economy_pop_cod';
    public const METHOD_ECONOMY_BOX = 'economy_box';
    public const METHOD_ECONOMY_BOX_COD = 'economy_box_cod';
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

    private const ECONOMY_POINT_METHODS = [
        self::METHOD_ECONOMY_ALL,
        self::METHOD_ECONOMY_ALL_COD,
        self::METHOD_ECONOMY_POP,
        self::METHOD_ECONOMY_POP_COD,
        self::METHOD_ECONOMY_BOX,
        self::METHOD_ECONOMY_BOX_COD,
    ];

    private const PACKSTATION_METHODS = [
        self::METHOD_CONNECT_PACKSTATION,
        self::METHOD_CONNECT_PACKSTATION_COD,
    ];

    private const PARCELSHOP_METHODS = [
        self::METHOD_CONNECT_PARCELSHOP,
        self::METHOD_CONNECT_PARCELSHOP_COD,
    ];

    /**
     * @var array
     */
    private const INTERNATIONAL_MAP_POINT_METHODS = [
        self::METHOD_CONNECT_PACKSTATION => InternationalMapPointType::TYPE_PACKSTATION,
        self::METHOD_CONNECT_PACKSTATION_COD => InternationalMapPointType::TYPE_PACKSTATION,
        self::METHOD_CONNECT_PARCELSHOP => InternationalMapPointType::TYPE_PARCELSHOP,
        self::METHOD_CONNECT_PARCELSHOP_COD => InternationalMapPointType::TYPE_PARCELSHOP,
    ];

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
        self::METHOD_ECONOMY_ALL => self::SERVICE_SP,
        self::METHOD_ECONOMY_ALL_COD => self::SERVICE_SP,
        self::METHOD_ECONOMY_POP => self::SERVICE_SP,
        self::METHOD_ECONOMY_POP_COD => self::SERVICE_SP,
        self::METHOD_ECONOMY_BOX => self::SERVICE_SP,
        self::METHOD_ECONOMY_BOX_COD => self::SERVICE_SP,
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
     * Determine whether a clean DHL method code accepts a service point.
     *
     * @param string $methodCode
     *
     * @return bool
     */
    public function isServicePointMethod(string $methodCode): bool
    {
        return in_array($methodCode, self::ECONOMY_POINT_METHODS, true)
            || $this->isPackstationMethod($methodCode)
            || $this->isParcelShopMethod($methodCode);
    }

    /**
     * Determine whether a clean DHL method code delivers to a Packstation.
     *
     * @param string $methodCode
     *
     * @return bool
     */
    public function isPackstationMethod(string $methodCode): bool
    {
        return in_array($methodCode, self::PACKSTATION_METHODS, true);
    }

    /**
     * Determine whether a clean DHL method code delivers to a parcel shop.
     *
     * @param string $methodCode
     *
     * @return bool
     */
    public function isParcelShopMethod(string $methodCode): bool
    {
        return in_array($methodCode, self::PARCELSHOP_METHODS, true);
    }

    /**
     * Resolve the international map point type from a Magento shipping method code.
     *
     * @param string|null $shippingMethod
     *
     * @return string
     */
    public function getInternationalMapPointTypeForMethod(?string $shippingMethod): string
    {
        $code = $this->normalizeMethodCode($shippingMethod);

        return self::INTERNATIONAL_MAP_POINT_METHODS[$code] ?? InternationalMapPointType::TYPE_COURIER;
    }

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
                'title' => __('Kurier DHL'),
                'service_code' => self::SERVICE_AH,
            ],
            [
                'code' => self::METHOD_PARCEL_POLSKA_COD,
                'title' => __('Kurier DHL - płatność przy odbiorze'),
                'service_code' => self::SERVICE_AH,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_PREMIUM,
                'title' => __('Kurier DHL Premium'),
                'service_code' => self::SERVICE_PR,
            ],
            [
                'code' => self::METHOD_PREMIUM_COD,
                'title' => __('Kurier DHL Premium - płatność przy odbiorze'),
                'service_code' => self::SERVICE_PR,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_PARCEL_9,
                'title' => __('Kurier DHL – gwarancja dostawy do godziny 9:00'),
                'service_code' => self::SERVICE_09,
            ],
            [
                'code' => self::METHOD_PARCEL_9_COD,
                'title' => __('Kurier DHL – gwarancja dostawy do godziny 9:00 - płatność przy odbiorze'),
                'service_code' => self::SERVICE_09,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_PARCEL_12,
                'title' => __('Kurier DHL – gwarancja dostawy do godziny 12:00'),
                'service_code' => self::SERVICE_12,
            ],
            [
                'code' => self::METHOD_PARCEL_12_COD,
                'title' => __('Kurier DHL – gwarancja dostawy do godziny 12:00 - płatność przy odbiorze'),
                'service_code' => self::SERVICE_12,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_ECONOMY_ALL,
                'title' => __('Automaty DHL BOX i punkty POP (Dostawa także w soboty)'),
                'service_code' => self::SERVICE_SP,
            ],
            [
                'code' => self::METHOD_ECONOMY_ALL_COD,
                'title' => __('Automaty DHL BOX i punkty POP (Dostawa także w soboty) - płatność przy odbiorze'),
                'service_code' => self::SERVICE_SP,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_ECONOMY_POP,
                'title' => __('DHL POP (Dostawa także w soboty)'),
                'service_code' => self::SERVICE_SP,
            ],
            [
                'code' => self::METHOD_ECONOMY_POP_COD,
                'title' => __('DHL POP (Dostawa także w soboty) - płatność przy odbiorze'),
                'service_code' => self::SERVICE_SP,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_ECONOMY_BOX,
                'title' => __('Automaty DHL BOX (Dostawa także w soboty)'),
                'service_code' => self::SERVICE_SP,
            ],
            [
                'code' => self::METHOD_ECONOMY_BOX_COD,
                'title' => __('Automaty DHL BOX (Dostawa także w soboty) - płatność przy odbiorze'),
                'service_code' => self::SERVICE_SP,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_MAX_PALETA,
                'title' => __('Kurier DHL - przesyłka paletowa'),
                'service_code' => self::SERVICE_AH,
            ],
            [
                'code' => self::METHOD_MAX_PALETA_COD,
                'title' => __('Kurier DHL - przesyłka paletowa - płatność przy odbiorze'),
                'service_code' => self::SERVICE_AH,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT,
                'title' => __('DHL Connect'),
                'service_code' => self::SERVICE_EK,
            ],
            [
                'code' => self::METHOD_CONNECT_COD,
                'title' => __('DHL Connect - Cash on Delivery'),
                'service_code' => self::SERVICE_EK,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PARCELSHOP,
                'title' => __('DHL Connect Parcelshop'),
                'service_code' => self::SERVICE_EK,
            ],
            [
                'code' => self::METHOD_CONNECT_PARCELSHOP_COD,
                'title' => __('DHL Connect Parcelshop - Cash on Delivery'),
                'service_code' => self::SERVICE_EK,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PACKSTATION,
                'title' => __('DHL Connect Packstation'),
                'service_code' => self::SERVICE_EK,
            ],
            [
                'code' => self::METHOD_CONNECT_PACKSTATION_COD,
                'title' => __('DHL Connect Packstation - Cash on Delivery'),
                'service_code' => self::SERVICE_EK,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PLUS,
                'title' => __('DHL Connect Plus'),
                'service_code' => self::SERVICE_CP,
            ],
            [
                'code' => self::METHOD_CONNECT_PLUS_COD,
                'title' => __('DHL Connect Plus - Cash on Delivery'),
                'service_code' => self::SERVICE_CP,
                'is_cod' => true,
            ],
            [
                'code' => self::METHOD_CONNECT_PALLET,
                'title' => __('DHL Connect Pallet'),
                'service_code' => self::SERVICE_CM,
            ],
            [
                'code' => self::METHOD_CONNECT_PALLET_COD,
                'title' => __('DHL Connect Pallet - Cash on Delivery'),
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
        $code = $this->normalizeMethodCode($shippingMethod);
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
        $code = $this->normalizeMethodCode($shippingMethod);
        return str_ends_with($code, '_cod');
    }

    /**
     * Normalize a Magento shipping method code to the DHL method code.
     *
     * @param string|null $shippingMethod
     *
     * @return string
     */
    private function normalizeMethodCode(?string $shippingMethod): string
    {
        $code = strtolower(trim((string)$shippingMethod));
        if (str_starts_with($code, Carrier::CODE_PREFIX)) {
            return substr($code, strlen(Carrier::CODE_PREFIX));
        }

        return $code;
    }
}
