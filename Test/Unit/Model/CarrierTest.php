<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\RateCalculator;
use Ideo\Dhl\Model\TableRateResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CarrierTest extends TestCase
{
    /**
     * Verify a disabled carrier does not return shipping rates.
     */
    public function testDisabledCarrierReturnsNoRates(): void
    {
        $carrier = $this->createCarrier(['active' => '0']);

        self::assertFalse($carrier->collectRates($this->createStub(RateRequest::class)));
    }

    /**
     * Verify only enabled methods are returned with their configured values.
     */
    public function testOnlyEnabledMethodsAreReturnedWithConfiguredTitleAndPrice(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_title' => 'Polska dostawa',
            'method_parcel_polska_price' => '12.50',
            'method_premium_active' => '0',
        ];
        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $result = $carrier->collectRates($this->createStub(RateRequest::class));

        self::assertInstanceOf(Result::class, $result);
        self::assertCount(1, $rates);
        self::assertSame('parcel_polska', $rates[0]['method']);
        self::assertSame('Polska dostawa', $rates[0]['title']);
        self::assertSame(12.5, $rates[0]['price']);
    }

    /**
     * Verify every catalog method can be enabled independently.
     */
    public function testEveryCatalogMethodCanBeEnabledIndependently(): void
    {
        $config = ['active' => '1', 'title' => 'DHL Parcel'];
        foreach ((new MethodCatalog())->getMethods() as $method) {
            $config['method_' . $method['code'] . '_active'] = '1';
            $config['method_' . $method['code'] . '_price'] = '3.25';
            $config['method_' . $method['code'] . '_title'] = $method['title'];
        }
        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $carrier->collectRates($this->createStub(RateRequest::class));

        self::assertCount(15, $rates);
    }

    /**
     * Verify methods restricted to specific countries are excluded for other destination countries.
     */
    public function testMethodsRestrictedToSpecificCountriesAreExcludedForOtherDestinations(): void
    {
        $config = [
            'active' => '1',
            'title' => 'DHL Parcel',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_title' => 'DHL Parcel Polska',
            'method_parcel_polska_price' => '10.00',
            'method_parcel_polska_sallowspecific' => '1',
            'method_parcel_polska_specificcountry' => 'PL',

            'method_connect_active' => '1',
            'method_connect_title' => 'DHL Parcel Connect',
            'method_connect_price' => '30.00',
            'method_connect_sallowspecific' => '0',
            'method_connect_specificcountry' => '',
        ];

        // Case A: Shipping to DE (Germany) -> parcel_polska should be excluded, connect should be included
        $requestDe = new RateRequest();
        $requestDe->setDestCountryId('DE');
        $ratesDe = [];
        $carrierDe = $this->createCarrier($config, $ratesDe);
        $carrierDe->collectRates($requestDe);

        self::assertCount(1, $ratesDe);
        self::assertSame('connect', $ratesDe[0]['method']);

        // Case B: Shipping to PL (Poland) -> both parcel_polska and connect should be included
        $requestPl = new RateRequest();
        $requestPl->setDestCountryId('PL');
        $ratesPl = [];
        $carrierPl = $this->createCarrier($config, $ratesPl);
        $carrierPl->collectRates($requestPl);

        self::assertCount(2, $ratesPl);
    }

    /**
     * Verify method sort order is applied to rate results.
     */
    public function testMethodSortOrderIsAppliedToRateResults(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_title' => 'DHL Parcel Polska',
            'method_parcel_polska_price' => '10.00',
            'method_parcel_polska_sort_order' => '42',
        ];
        $rates = [];
        $carrier = $this->createCarrier($config, $rates);
        $carrier->collectRates(new RateRequest());

        self::assertCount(1, $rates);
        self::assertSame(42, $rates[0]['sort_order']);
    }

    /**
     * Verify free shipping threshold applies per method when subtotal threshold is reached.
     */
    public function testFreeShippingThresholdIsAppliedPerMethod(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_title' => 'DHL Parcel Polska',
            'method_parcel_polska_price' => '15.00',
            'method_parcel_polska_free_shipping_enable' => '1',
            'method_parcel_polska_free_shipping_subtotal' => '150.00',
        ];
        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $request = new RateRequest();
        $request->setPackageValueWithDiscount(200.00);

        $carrier->collectRates($request);

        self::assertCount(1, $rates);
        self::assertSame(0.0, $rates[0]['price']);
    }

    /**
     * Verify non-applicable method returns error rate when showmethod is enabled.
     */
    public function testShowMethodIfNotApplicableReturnsErrorRate(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_title' => 'DHL Parcel Polska',
            'method_parcel_polska_price' => '15.00',
            'method_parcel_polska_sallowspecific' => '1',
            'method_parcel_polska_specificcountry' => 'PL',
            'method_parcel_polska_showmethod' => '1',
            'method_parcel_polska_specificerrmsg' => 'Metoda niedostępna dla tego kraju.',
        ];
        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $request = new RateRequest();
        $request->setDestCountryId('DE');

        $carrier->collectRates($request);

        self::assertCount(1, $rates);
        self::assertSame('Metoda niedostępna dla tego kraju.', $rates[0]['error_message']);
    }

    /**
     * Create a carrier with isolated configuration and rate factories.
     *
     * @param array $config
     * @param array $rates
     */
    private function createCarrier(array $config, array &$rates = []): Carrier
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use ($config) {
                return $config[str_replace('carriers/dhlparcel/', '', $path)] ?? null;
            }
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static function (string $path) use ($config): bool {
                return (bool)($config[str_replace('carriers/dhlparcel/', '', $path)] ?? false);
            }
        );

        $result = $this->createStub(Result::class);
        $result->method('append')->willReturnCallback(function (DataObject $rate) use (&$rates, $result): Result {
            $rates[] = [
                'method' => $rate->getMethod(),
                'title' => $rate->getMethodTitle(),
                'price' => $rate->getPrice(),
                'sort_order' => $rate->getSortOrder(),
                'error_message' => $rate->getErrorMessage(),
            ];
            return $result;
        });
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($result);

        $methodFactory = $this->createStub(MethodFactory::class);
        $methodFactory->method('create')->willReturnCallback(static fn (): DataObject => new DataObject());

        $errorFactory = $this->createStub(ErrorFactory::class);
        $errorFactory->method('create')->willReturnCallback(static fn (): DataObject => new DataObject());

        $tableRateResolver = $this->createStub(TableRateResolver::class);
        $rateCalculator = new RateCalculator($tableRateResolver);

        return new Carrier(
            $scopeConfig,
            $errorFactory,
            $this->createStub(LoggerInterface::class),
            $resultFactory,
            $methodFactory,
            new MethodCatalog(),
            $rateCalculator
        );
    }
}
