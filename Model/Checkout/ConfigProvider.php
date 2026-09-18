<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Ideo\Dhl\Model\Checkout;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\Config\Source\MapPointType;
use Ideo\Dhl\Model\MethodCatalog;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class ConfigProvider implements ConfigProviderInterface
{
    private const XML_PATH_MAP_TYPE = 'carriers/dhlparcel/method_economy_point_map_type';
    private const XML_PATH_DELIVERY_MODE = 'carriers/dhlparcel/method_economy_point_delivery_mode';
    private const XML_PATH_DEBUG = 'carriers/dhlparcel/debug';
    public const DEFAULT_MAP_URL = 'https://parcelshop.dhl.pl/mapa';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CheckoutSession $checkoutSession
     * @param AssetRepository $assetRepository
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CheckoutSession $checkoutSession,
        private readonly AssetRepository $assetRepository
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
        ) ?: MapPointType::TYPE_ALL;

        $deliveryMode = (string)$this->scopeConfig->getValue(
            self::XML_PATH_DELIVERY_MODE,
            ScopeInterface::SCOPE_STORE
        ) ?: 'map';

        $debug = $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE
        );

        $economyCode = MethodCatalog::METHOD_ECONOMY;
        $economyPrefixed = Carrier::CODE_PREFIX . $economyCode;

        $economyCodCode = MethodCatalog::METHOD_ECONOMY_COD;
        $economyCodPrefixed = Carrier::CODE_PREFIX . $economyCodCode;

        $connectParcelshopCode = MethodCatalog::METHOD_CONNECT_PARCELSHOP;
        $connectParcelshopPrefixed = Carrier::CODE_PREFIX . $connectParcelshopCode;

        $connectParcelshopCodCode = MethodCatalog::METHOD_CONNECT_PARCELSHOP_COD;
        $connectParcelshopCodPrefixed = Carrier::CODE_PREFIX . $connectParcelshopCodCode;

        $connectPackstationCode = MethodCatalog::METHOD_CONNECT_PACKSTATION;
        $connectPackstationPrefixed = Carrier::CODE_PREFIX . $connectPackstationCode;

        $connectPackstationCodCode = MethodCatalog::METHOD_CONNECT_PACKSTATION_COD;
        $connectPackstationCodPrefixed = Carrier::CODE_PREFIX . $connectPackstationCodCode;

        $pointMapTypes = [
            $economyPrefixed => (string)$this->scopeConfig->getValue(
                'carriers/dhlparcel/method_economy_point_map_type',
                ScopeInterface::SCOPE_STORE
            ) ?: MapPointType::TYPE_ALL,
            $economyCode => (string)$this->scopeConfig->getValue(
                'carriers/dhlparcel/method_economy_point_map_type',
                ScopeInterface::SCOPE_STORE
            ) ?: MapPointType::TYPE_ALL,
            $economyCodPrefixed => (string)$this->scopeConfig->getValue(
                'carriers/dhlparcel/method_economy_cod_point_map_type',
                ScopeInterface::SCOPE_STORE
            ) ?: ((string)$this->scopeConfig->getValue(
                'carriers/dhlparcel/method_economy_point_map_type',
                ScopeInterface::SCOPE_STORE
            ) ?: MapPointType::TYPE_ALL),
            $economyCodCode => (string)$this->scopeConfig->getValue(
                'carriers/dhlparcel/method_economy_cod_point_map_type',
                ScopeInterface::SCOPE_STORE
            ) ?: ((string)$this->scopeConfig->getValue(
                'carriers/dhlparcel/method_economy_point_map_type',
                ScopeInterface::SCOPE_STORE
            ) ?: MapPointType::TYPE_ALL),
            $connectParcelshopPrefixed => MapPointType::TYPE_POP,
            $connectParcelshopCode => MapPointType::TYPE_POP,
            $connectParcelshopCodPrefixed => MapPointType::TYPE_POP,
            $connectParcelshopCodCode => MapPointType::TYPE_POP,
            $connectPackstationPrefixed => MapPointType::TYPE_BOX,
            $connectPackstationCode => MapPointType::TYPE_BOX,
            $connectPackstationCodPrefixed => MapPointType::TYPE_BOX,
            $connectPackstationCodCode => MapPointType::TYPE_BOX,
        ];

        return [
            'shipping' => [
                'dhlparcel' => [
                    'mapUrl' => self::DEFAULT_MAP_URL,
                    'pointMapType' => $mapType,
                    'pointMapTypes' => $pointMapTypes,
                    'deliveryMode' => $deliveryMode,
                    'debug' => $debug,
                    'selectedPoint' => $this->getQuoteSelectedPoint(),
                    'logos' => [
                        $economyPrefixed => $this->assetRepository->getUrl('Ideo_Dhl::images/logo_dhl_box_pop.png'),
                        $economyCodPrefixed => $this->assetRepository->getUrl('Ideo_Dhl::images/logo_dhl_box_pop.png'),
                        $connectParcelshopPrefixed => $this->assetRepository->getUrl('Ideo_Dhl::images/logo_dhl.png'),
                        $connectParcelshopCodPrefixed => $this->assetRepository->getUrl('Ideo_Dhl::images/logo_dhl.png'),
                        $connectPackstationPrefixed => $this->assetRepository->getUrl('Ideo_Dhl::images/logo_dhl.png'),
                        $connectPackstationCodPrefixed => $this->assetRepository->getUrl('Ideo_Dhl::images/logo_dhl.png'),
                        'default' => $this->assetRepository->getUrl('Ideo_Dhl::images/logo_dhl.png'),
                    ],
                    'pointMethods' => [
                        $economyPrefixed,
                        $economyCodPrefixed,
                        $connectParcelshopPrefixed,
                        $connectParcelshopCodPrefixed,
                        $connectPackstationPrefixed,
                        $connectPackstationCodPrefixed,
                    ],
                ],
            ],
        ];
    }

    /**
     * Retrieve DHL service point saved on the active quote shipping address if present.
     *
     * @return array|null
     */
    private function getQuoteSelectedPoint(): ?array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return null;
            }

            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress) {
                return null;
            }

            $sap = (string) $shippingAddress->getData('dhl_service_point_id');
            if (trim($sap) === '') {
                return null;
            }

            $name = (string) $shippingAddress->getData('dhl_service_point_name');
            $street = (string) $shippingAddress->getData('dhl_service_point_street');
            $postcode = (string) $shippingAddress->getData('dhl_service_point_postcode');
            $city = (string) $shippingAddress->getData('dhl_service_point_city');

            return [
                'sap' => $sap,
                'name' => $name !== '' ? $name : $sap,
                'street' => $street,
                'streetNo' => '',
                'houseNo' => '',
                'zip' => $postcode,
                'city' => $city,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
