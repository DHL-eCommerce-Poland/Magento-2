<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory as RateErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory as RateMethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'dhlparcel';

    /**
     * Magento carrier code.
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @param ScopeConfigInterface $scopeConfig Carrier configuration provider.
     * @param RateErrorFactory $rateErrorFactory Factory for carrier errors.
     * @param LoggerInterface $logger Diagnostic logger.
     * @param ResultFactory $resultFactory Factory for shipping rate results.
     * @param RateMethodFactory $rateMethodFactory Factory for individual shipping rates.
     * @param MethodCatalog $methodCatalog Catalog of supported DHL Parcel methods.
     * @param array $data Additional carrier data.
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        RateErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $resultFactory,
        private readonly RateMethodFactory $rateMethodFactory,
        private readonly MethodCatalog $methodCatalog,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Collect configured DHL Parcel rates without contacting the DHL API.
     *
     * @param RateRequest $request Magento shipping rate request.
     *
     * @return Result|false Shipping rates, or false when the carrier is disabled.
     */
    public function collectRates(RateRequest $request): Result|bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $result = $this->resultFactory->create();

        $destCountryId = $request->getDestCountryId();

        foreach ($this->methodCatalog->getMethods() as $method) {
            $code = $method['code'];

            if (!$this->getConfigFlag('method_' . $code . '_active')) {
                continue;
            }

            if ($this->isMethodDestinationRestricted($code, $destCountryId)) {
                if ($this->getConfigFlag('method_' . $code . '_showmethod')) {
                    $error = $this->_rateErrorFactory->create();
                    $error->setCarrier(self::CODE);
                    $error->setCarrierTitle('DHL Parcel');
                    $error->setMethod($code);
                    $errMsg = $this->getConfigData('method_' . $code . '_specificerrmsg')
                        ?: __('This shipping method is currently unavailable.');
                    $error->setErrorMessage((string)$errMsg);
                    $result->append($error);
                }
                continue;
            }

            $price = (float)$this->getConfigData('method_' . $code . '_price');
            $isFreeShippingEnabled = $this->getConfigFlag('method_' . $code . '_free_shipping_enable');
            $freeShippingSubtotal = (float)$this->getConfigData('method_' . $code . '_free_shipping_subtotal');
            $packageValue = (float)$request->getPackageValueWithDiscount();

            if ($request->getFreeShipping() === true
                || ($isFreeShippingEnabled && $packageValue >= $freeShippingSubtotal)
            ) {
                $price = 0.00;
            }

            $rate = $this->rateMethodFactory->create();
            $rate->setCarrier(self::CODE);
            $rate->setCarrierTitle('DHL Parcel');
            $rate->setMethod($code);
            $methodTitle = $this->getConfigData('method_' . $code . '_title') ?: $method['title'];
            $rate->setMethodTitle((string)$methodTitle);
            $rate->setPrice($price);
            $rate->setCost($price);
            $sortOrder = (int)$this->getConfigData('method_' . $code . '_sort_order');
            $rate->setSortOrder($sortOrder);
            $result->append($rate);
        }

        return $result;
    }

    /**
     * Check if a shipping method is restricted for the given destination country.
     *
     * @param string $methodCode
     * @param string|null $destCountryId
     *
     * @return bool
     */
    private function isMethodDestinationRestricted(string $methodCode, ?string $destCountryId): bool
    {
        $sallowspecific = (int)$this->getConfigData('method_' . $methodCode . '_sallowspecific');
        if ($sallowspecific === 1) {
            $specificCountryConfig = (string)$this->getConfigData('method_' . $methodCode . '_specificcountry');
            $allowedCountries = array_filter(array_map('trim', explode(',', $specificCountryConfig)));
            if (!in_array($destCountryId, $allowedCountries, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return all services known by this carrier for Magento's carrier API.
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        $methods = [];
        foreach ($this->methodCatalog->getMethods() as $method) {
            $methods[$method['code']] = $method['title'];
        }

        return $methods;
    }
}
