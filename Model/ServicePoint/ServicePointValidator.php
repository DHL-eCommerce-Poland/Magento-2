<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace DhlPoland\Dhl\Model\ServicePoint;

use DhlPoland\Dhl\Model\Carrier;
use DhlPoland\Dhl\Model\MethodCatalog;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

class ServicePointValidator
{
    private const XML_PATH_DELIVERY_MODE = 'carriers/dhlpoland/method_economy_all_point_delivery_mode';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param MethodCatalog $methodCatalog
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly MethodCatalog $methodCatalog
    ) {
    }

    /**
     * Validate pickup point selection for a given carrier and shipping method.
     *
     * @param string|null $carrierCode
     * @param string|null $methodCode
     * @param ServicePointValueObject|null $servicePoint
     *
     * @return void
     * @throws LocalizedException
     */
    public function validate(
        ?string $carrierCode,
        ?string $methodCode,
        ?ServicePointValueObject $servicePoint
    ): void {
        if (!$this->isPointSelectionRequired($carrierCode, $methodCode)) {
            return;
        }

        if ($servicePoint === null || !$servicePoint->isValid()) {
            throw new LocalizedException(__('Please select a DHL pickup point on the map.'));
        }

        $country = strtoupper((string) $servicePoint->getCountry());
        $postnummer = trim((string) $servicePoint->getPostnummer());

        $cleanMethodCode = strtolower(trim((string) $methodCode));
        if (str_starts_with($cleanMethodCode, Carrier::CODE_PREFIX)) {
            $cleanMethodCode = substr($cleanMethodCode, strlen(Carrier::CODE_PREFIX));
        }

        $isPackstationMethod = in_array(
            $cleanMethodCode,
            [
                MethodCatalog::METHOD_CONNECT_PACKSTATION,
                MethodCatalog::METHOD_CONNECT_PACKSTATION_COD,
            ],
            true
        );

        if ($country === 'DE' && $isPackstationMethod) {
            if ($postnummer === '') {
                throw new LocalizedException(__('German Customer Number (Postnummer) is required for delivery to a German Packstation.'));
            }

            if (!preg_match('/^\d{6,10}$/', $postnummer)) {
                throw new LocalizedException(__('Invalid German Customer Number (Postnummer). It must contain between 6 and 10 digits.'));
            }
        }
    }

    /**
     * Determine whether point selection from map is required for the method.
     *
     * @param string|null $carrierCode
     * @param string|null $methodCode
     *
     * @return bool
     */
    public function isPointSelectionRequired(?string $carrierCode, ?string $methodCode): bool
    {
        if (empty($carrierCode) || empty($methodCode)) {
            return false;
        }

        if ($carrierCode !== Carrier::CODE) {
            return false;
        }

        $cleanMethodCode = strtolower(trim($methodCode));
        if (str_starts_with($cleanMethodCode, Carrier::CODE_PREFIX)) {
            $cleanMethodCode = substr($cleanMethodCode, strlen(Carrier::CODE_PREFIX));
        }

        if (!$this->methodCatalog->isServicePointMethod($cleanMethodCode)) {
            return false;
        }

        // Domestic SP (economy) in 'nearest' delivery mode does NOT require map point selection
        if ($cleanMethodCode === MethodCatalog::METHOD_ECONOMY_ALL || $cleanMethodCode === MethodCatalog::METHOD_ECONOMY_ALL_COD) {
            $path = $cleanMethodCode === MethodCatalog::METHOD_ECONOMY_ALL_COD
                ? 'carriers/dhlpoland/method_economy_all_cod_point_delivery_mode'
                : self::XML_PATH_DELIVERY_MODE;

            $deliveryMode = (string) $this->scopeConfig->getValue(
                $path,
                ScopeInterface::SCOPE_STORE
            ) ?: ((string) $this->scopeConfig->getValue(
                self::XML_PATH_DELIVERY_MODE,
                ScopeInterface::SCOPE_STORE
            ) ?: 'map');

            if ($deliveryMode === 'nearest') {
                return false;
            }
        }

        return true;
    }
}
