<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\RateCalculator;
use Ideo\Dhl\Model\TableRateResolver;
use Ideo\Dhl\Model\TrackingUrlResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\Status;
use Magento\Shipping\Model\Tracking\Result\StatusFactory as TrackStatusFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CarrierTest extends TestCase
{
    /**
     * Verify disabled carrier returns false.
     */
    public function testDisabledCarrierReturnsNoRates(): void
    {
        $carrier = $this->createCarrier(['active' => '0']);
        $request = new RateRequest();
        $this->assertFalse($carrier->collectRates($request));
    }

    /**
     * Verify active carrier returns enabled methods.
     */
    public function testOnlyEnabledMethodsAreReturnedWithConfiguredTitleAndPrice(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_pricing_type' => 'fixed',
            'method_parcel_polska_price' => '25.50',
            'method_parcel_polska_title' => 'DHL Parcel Polska',
            'method_parcel_polska_method_name' => 'Przesyłka kurierska',
            'method_premium_active' => '0',
        ];

        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $request = new RateRequest();
        $request->setPackageWeight(2.0);
        $request->setPackageValueWithDiscount(100.0);

        $result = $carrier->collectRates($request);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertCount(1, $rates);
        $this->assertSame('parcel_polska', $rates[0]['method']);
        $this->assertSame(25.50, $rates[0]['price']);
        $this->assertSame('DHL Parcel Polska', $rates[0]['carrier_title']);
        $this->assertSame('Przesyłka kurierska', $rates[0]['title']);
    }

    /**
     * Verify each catalog method can be enabled independently.
     */
    public function testEveryCatalogMethodCanBeEnabledIndependently(): void
    {
        $catalog = new MethodCatalog();

        foreach ($catalog->getMethods() as $method) {
            $code = $method['code'];
            $config = [
                'active' => '1',
                'method_' . $code . '_active' => '1',
                'method_' . $code . '_pricing_type' => 'fixed',
                'method_' . $code . '_price' => '10.00',
            ];

            $rates = [];
            $carrier = $this->createCarrier($config, $rates);

            $request = new RateRequest();
            $request->setPackageWeight(1.0);
            $request->setPackageValueWithDiscount(50.0);

            $carrier->collectRates($request);

            $this->assertCount(1, $rates, 'Failed asserting method code: ' . $code);
            $this->assertSame($code, $rates[0]['method']);
        }
    }

    /**
     * Verify destination country restrictions.
     */
    public function testMethodsRestrictedToSpecificCountriesAreExcludedForOtherDestinations(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_pricing_type' => 'fixed',
            'method_parcel_polska_price' => '20.00',
            'method_parcel_polska_sallowspecific' => '1',
            'method_parcel_polska_specificcountry' => 'PL,DE',
        ];

        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $allowedRequest = new RateRequest();
        $allowedRequest->setDestCountryId('PL');
        $carrier->collectRates($allowedRequest);
        $this->assertCount(1, $rates);

        $rates = [];
        $deniedRequest = new RateRequest();
        $deniedRequest->setDestCountryId('FR');
        $carrier->collectRates($deniedRequest);
        $this->assertCount(0, $rates);
    }

    /**
     * Verify sort order is passed to rate objects.
     */
    public function testMethodSortOrderIsAppliedToRateResults(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_pricing_type' => 'fixed',
            'method_parcel_polska_price' => '20.00',
            'method_parcel_polska_sort_order' => '15',
        ];

        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $request = new RateRequest();
        $carrier->collectRates($request);

        $this->assertSame(15, $rates[0]['sort_order']);
    }

    /**
     * Verify free shipping threshold logic integration.
     */
    public function testFreeShippingThresholdIsAppliedPerMethod(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_pricing_type' => 'fixed',
            'method_parcel_polska_price' => '20.00',
            'method_parcel_polska_free_shipping_enable' => '1',
            'method_parcel_polska_free_shipping_subtotal' => '150.00',
        ];

        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $request = new RateRequest();
        $request->setPackageValueWithDiscount(200.0);
        $carrier->collectRates($request);

        $this->assertSame(0.00, $rates[0]['price']);
    }

    /**
     * Verify showmethod flag appends error message when restricted.
     */
    public function testShowMethodIfNotApplicableReturnsErrorRate(): void
    {
        $config = [
            'active' => '1',
            'method_parcel_polska_active' => '1',
            'method_parcel_polska_sallowspecific' => '1',
            'method_parcel_polska_specificcountry' => 'PL',
            'method_parcel_polska_showmethod' => '1',
            'method_parcel_polska_specificerrmsg' => 'Method not allowed for destination.',
        ];

        $rates = [];
        $carrier = $this->createCarrier($config, $rates);

        $request = new RateRequest();
        $request->setDestCountryId('US');
        $carrier->collectRates($request);

        $this->assertCount(1, $rates);
        $this->assertSame('Method not allowed for destination.', $rates[0]['error_message']);
    }

    /**
     * Verify tracking availability getter.
     */
    public function testIsTrackingAvailableReturnsTrue(): void
    {
        $carrier = $this->createCarrier([]);
        $this->assertTrue($carrier->isTrackingAvailable());
    }

    /**
     * Verify tracking lookup returns false when tracking number is empty.
     */
    public function testGetTrackingInfoReturnsFalseForEmptyTrackingNumber(): void
    {
        $carrier = $this->createCarrier([]);
        $this->assertFalse($carrier->getTrackingInfo(''));
    }

    /**
     * Verify tracking lookup returns status object with correct title and URL.
     */
    public function testGetTrackingInfoReturnsStatusObjectWithUrlAndTitle(): void
    {
        $carrier = $this->createCarrier([]);
        $status = $carrier->getTrackingInfo('123456789');

        $this->assertInstanceOf(Status::class, $status);
        $this->assertSame('123456789', $status->getTracking());
        $this->assertSame('DHL eCommerce Poland', $status->getCarrierTitle());
        $this->assertStringContainsString('123456789', $status->getUrl());
    }

    /**
     * Verify method is excluded when rate calculator returns null for country.
     */
    public function testMethodIsExcludedWhenRateCalculatorReturnsNullForCountry(): void
    {
        $config = [
            'active' => '1',
            'method_connect_active' => '1',
            'method_connect_title' => 'DHL Parcel Connect',
            'method_connect_pricing_type' => 'country',
        ];

        $tableRateResolverMock = $this->createMock(TableRateResolver::class);
        $tableRateResolverMock->expects($this->once())
            ->method('resolveRate')
            ->with(0, 'connect', 'ES', 'weight', 0.0)
            ->willReturn(null);

        $rates = [];
        $carrier = $this->createCarrier($config, $rates, $tableRateResolverMock);

        $request = new RateRequest();
        $request->setDestCountryId('ES');

        $carrier->collectRates($request);

        self::assertCount(0, $rates);
    }

    /**
     * Verify method is included with country rate price when rate calculator returns price.
     */
    public function testMethodIsIncludedWithCountryRatePriceWhenRateCalculatorReturnsPrice(): void
    {
        $config = [
            'active' => '1',
            'method_connect_active' => '1',
            'method_connect_title' => 'DHL Parcel Connect',
            'method_connect_pricing_type' => 'country',
        ];

        $tableRateResolverMock = $this->createMock(TableRateResolver::class);
        $tableRateResolverMock->expects($this->once())
            ->method('resolveRate')
            ->with(0, 'connect', 'DE', 'weight', 0.0)
            ->willReturn(43.00);

        $rates = [];
        $carrier = $this->createCarrier($config, $rates, $tableRateResolverMock);

        $request = new RateRequest();
        $request->setDestCountryId('DE');

        $carrier->collectRates($request);

        self::assertCount(1, $rates);
        self::assertSame('connect', $rates[0]['method']);
        self::assertSame(43.0, $rates[0]['price']);
    }

    /**
     * Create a carrier with isolated configuration and rate factories.
     *
     * @param array $config
     * @param array $rates
     * @param TableRateResolver|null $tableRateResolver
     */
    private function createCarrier(
        array $config,
        array &$rates = [],
        ?TableRateResolver $tableRateResolver = null
    ): Carrier {
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
                'carrier_title' => $rate->getCarrierTitle(),
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

        $trackStatusFactory = $this->createStub(TrackStatusFactory::class);
        $trackStatusFactory->method('create')->willReturnCallback(static fn (): Status => new Status());

        $tableRateResolver = $tableRateResolver ?? $this->createStub(TableRateResolver::class);
        $rateCalculator = new RateCalculator($tableRateResolver);

        return new Carrier(
            $scopeConfig,
            $errorFactory,
            $this->createStub(LoggerInterface::class),
            $resultFactory,
            $methodFactory,
            new MethodCatalog(),
            $rateCalculator,
            $trackStatusFactory,
            new TrackingUrlResolver()
        );
    }
}
