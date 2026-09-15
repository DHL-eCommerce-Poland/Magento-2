<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model;

use Ideo\Dhl\Model\CountryRateResolver;
use Ideo\Dhl\Model\RateCalculator;
use Ideo\Dhl\Model\TableRateResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RateCalculatorTest extends TestCase
{
    /**
     * @var TableRateResolver|MockObject
     */
    private $tableRateResolverMock;

    /**
     * @var CountryRateResolver|MockObject
     */
    private $countryRateResolverMock;

    /**
     * @var RateCalculator
     */
    private $rateCalculator;

    protected function setUp(): void
    {
        $this->tableRateResolverMock = $this->createMock(TableRateResolver::class);
        $this->countryRateResolverMock = $this->createMock(CountryRateResolver::class);
        $this->rateCalculator = new RateCalculator(
            $this->tableRateResolverMock,
            $this->countryRateResolverMock
        );
    }

    public function testCalculateReturnsZeroWhenFreeShippingRequested(): void
    {
        $this->tableRateResolverMock->expects($this->never())->method('resolveRate');
        $this->countryRateResolverMock->expects($this->never())->method('resolveRate');

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'parcel_polska',
            pricingType: RateCalculator::PRICING_TYPE_FIXED,
            packageWeight: 2.5,
            packageValue: 100.0,
            flatPrice: 15.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: true
        );

        $this->assertEquals(0.00, $price);
    }

    public function testCalculateReturnsZeroWhenFreeShippingThresholdMet(): void
    {
        $this->tableRateResolverMock->expects($this->never())->method('resolveRate');
        $this->countryRateResolverMock->expects($this->never())->method('resolveRate');

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'parcel_polska',
            pricingType: RateCalculator::PRICING_TYPE_FIXED,
            packageWeight: 2.5,
            packageValue: 250.0,
            flatPrice: 15.0,
            isFreeShippingEnabled: true,
            freeShippingSubtotal: 200.0,
            isFreeShipping: false
        );

        $this->assertEquals(0.00, $price);
    }

    public function testCalculateReturnsFlatPriceWhenPricingTypeIsFixed(): void
    {
        $this->tableRateResolverMock->expects($this->never())->method('resolveRate');
        $this->countryRateResolverMock->expects($this->never())->method('resolveRate');

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'parcel_polska',
            pricingType: RateCalculator::PRICING_TYPE_FIXED,
            packageWeight: 2.5,
            packageValue: 50.0,
            flatPrice: 15.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: false
        );

        $this->assertEquals(15.00, $price);
    }

    public function testCalculateReturnsTableRatePriceWhenWeightIsInRange(): void
    {
        $this->countryRateResolverMock->expects($this->never())->method('resolveRate');
        $this->tableRateResolverMock
            ->expects($this->once())
            ->method('resolveRate')
            ->with(1, 'parcel_polska', RateCalculator::PRICING_TYPE_WEIGHT, 2.5)
            ->willReturn(18.50);

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'parcel_polska',
            pricingType: RateCalculator::PRICING_TYPE_WEIGHT,
            packageWeight: 2.5,
            packageValue: 50.0,
            flatPrice: 15.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: false
        );

        $this->assertEquals(18.50, $price);
    }

    public function testCalculateReturnsZeroWhenWeightIsOutOfBounds(): void
    {
        $this->countryRateResolverMock->expects($this->never())->method('resolveRate');
        $this->tableRateResolverMock
            ->expects($this->once())
            ->method('resolveRate')
            ->with(1, 'parcel_polska', RateCalculator::PRICING_TYPE_WEIGHT, 0.0)
            ->willReturn(null);

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'parcel_polska',
            pricingType: RateCalculator::PRICING_TYPE_WEIGHT,
            packageWeight: 0.0,
            packageValue: 50.0,
            flatPrice: 15.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: false
        );

        $this->assertEquals(0.00, $price);
    }

    public function testCalculateReturnsTableRatePriceWhenSubtotalIsInRange(): void
    {
        $this->countryRateResolverMock->expects($this->never())->method('resolveRate');
        $this->tableRateResolverMock
            ->expects($this->once())
            ->method('resolveRate')
            ->with(1, 'parcel_polska', RateCalculator::PRICING_TYPE_SUBTOTAL, 120.0)
            ->willReturn(12.00);

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'parcel_polska',
            pricingType: RateCalculator::PRICING_TYPE_SUBTOTAL,
            packageWeight: 1.0,
            packageValue: 120.0,
            flatPrice: 15.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: false
        );

        $this->assertEquals(12.00, $price);
    }

    public function testCalculateReturnsCountryRatePriceWhenCountryFound(): void
    {
        $this->tableRateResolverMock->expects($this->never())->method('resolveRate');
        $this->countryRateResolverMock
            ->expects($this->once())
            ->method('resolveRate')
            ->with(1, 'connect', 'DE')
            ->willReturn(43.00);

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'connect',
            pricingType: RateCalculator::PRICING_TYPE_COUNTRY,
            packageWeight: 2.0,
            packageValue: 50.0,
            flatPrice: 70.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: false,
            destCountryId: 'DE'
        );

        $this->assertSame(43.00, $price);
    }

    public function testCalculateReturnsNullWhenCountryNotFound(): void
    {
        $this->tableRateResolverMock->expects($this->never())->method('resolveRate');
        $this->countryRateResolverMock
            ->expects($this->once())
            ->method('resolveRate')
            ->with(1, 'connect', 'ES')
            ->willReturn(null);

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'connect',
            pricingType: RateCalculator::PRICING_TYPE_COUNTRY,
            packageWeight: 2.0,
            packageValue: 50.0,
            flatPrice: 70.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: false,
            destCountryId: 'ES'
        );

        $this->assertNull($price);
    }

    public function testCalculateReturnsNullWhenCountryRateAndDestCountryIdEmpty(): void
    {
        $this->tableRateResolverMock->expects($this->never())->method('resolveRate');
        $this->countryRateResolverMock->expects($this->never())->method('resolveRate');

        $price = $this->rateCalculator->calculate(
            websiteId: 1,
            methodCode: 'connect',
            pricingType: RateCalculator::PRICING_TYPE_COUNTRY,
            packageWeight: 2.0,
            packageValue: 50.0,
            flatPrice: 70.0,
            isFreeShippingEnabled: false,
            freeShippingSubtotal: 0.0,
            isFreeShipping: false,
            destCountryId: null
        );

        $this->assertNull($price);
    }
}
