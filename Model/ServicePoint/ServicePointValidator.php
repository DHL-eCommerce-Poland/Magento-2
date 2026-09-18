<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Ideo\Dhl\Model\ServicePoint;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\MethodCatalog;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

class ServicePointValidator
{
    private const XML_PATH_DELIVERY_MODE = 'carriers/dhlparcel/method_economy_point_delivery_mode';

    private const POINT_METHODS = [
        MethodCatalog::METHOD_ECONOMY,
        MethodCatalog::METHOD_CONNECT,
        MethodCatalog::METHOD_CONNECT_PARCEL_SHOP,
        MethodCatalog::METHOD_CONNECT_PACKSTATION,
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Validate pickup point selection for a given carrier and shipping method.
     *
     * @param string|null $carrierCode
     * @param string|null $methodCode
     * @param ServicePointValueObject|null $servicePoint
     *
     * @throws LocalizedException
     *
     * @return void
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

        if ($country === 'DE' && $servicePoint->isPackstation()) {
            if ($postnummer === '') {
                throw new LocalizedException(__('German Customer Number (Postnummer) is required for delivery to a German Packstation.'));
            }
        }

        if ($country === 'DE' && $postnummer !== '') {
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

        if (!in_array($cleanMethodCode, self::POINT_METHODS, true)) {
            return false;
        }

        // Domestic SP (economy) in 'nearest' delivery mode does NOT require map point selection
        if ($cleanMethodCode === MethodCatalog::METHOD_ECONOMY) {
            $deliveryMode = (string) $this->scopeConfig->getValue(
                self::XML_PATH_DELIVERY_MODE,
                ScopeInterface::SCOPE_STORE
            ) ?: 'map';

            if ($deliveryMode === 'nearest') {
                return false;
            }
        }

        return true;
    }
}
