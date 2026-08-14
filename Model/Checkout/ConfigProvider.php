<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private const XML_PATH_MAP_TYPE = 'carriers/dhlparcel/method_economy_point_map_type';
    private const XML_PATH_DELIVERY_MODE = 'carriers/dhlparcel/method_economy_point_delivery_mode';
    private const XML_PATH_DEBUG = 'carriers/dhlparcel/debug';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Return configuration for frontend checkout.
     *
     * @return array
     */
    public function getConfig(): array
    {
        $mapType = (string)$this->scopeConfig->getValue(
            self::XML_PATH_MAP_TYPE,
            ScopeInterface::SCOPE_STORE
        ) ?: 'all';

        $deliveryMode = (string)$this->scopeConfig->getValue(
            self::XML_PATH_DELIVERY_MODE,
            ScopeInterface::SCOPE_STORE
        ) ?: 'map';

        $debug = $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE
        );

        return [
            'shipping' => [
                'dhlparcel' => [
                    'mapUrl' => 'https://parcelshop.dhl.pl/mapa',
                    'pointMapType' => $mapType,
                    'deliveryMode' => $deliveryMode,
                    'debug' => $debug,
                    'pointMethods' => [
                        'dhlparcel_economy',
                        'dhlparcel_connect',
                        'dhlparcel_international',
                        'dhlparcel_connect_plus',
                    ],
                ],
            ],
        ];
    }
}
