<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ServicePoint;

use Ideo\Dhl\Model\Carrier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

class ServicePointValidator
{
    private const XML_PATH_DELIVERY_MODE = 'carriers/dhlparcel/method_economy_point_delivery_mode';

    private const POINT_METHODS = [
        'dhlparcel_economy',
        'dhlparcel_connect',
        'dhlparcel_international',
        'dhlparcel_connect_plus',
        'economy',
        'connect',
        'international',
        'connect_plus',
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

        $fullMethodCode = $carrierCode . '_' . $methodCode;

        $isPointMethod = in_array($fullMethodCode, self::POINT_METHODS, true)
            || in_array($methodCode, self::POINT_METHODS, true);

        if (!$isPointMethod) {
            return false;
        }

        // Domestic SP (economy) in 'nearest' delivery mode does NOT require map point selection
        if ($methodCode === 'economy') {
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
