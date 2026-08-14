<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Checkout;

use Ideo\Dhl\Model\Checkout\ConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testGetConfigReturnsConfiguredValues(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                ['carriers/dhlparcel/method_economy_point_map_type', ScopeInterface::SCOPE_STORE, null, 'pop'],
                ['carriers/dhlparcel/method_economy_point_delivery_mode', ScopeInterface::SCOPE_STORE, null, 'map'],
            ]);

        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('carriers/dhlparcel/debug', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->expects($this->once())
            ->method('getQuote')
            ->willReturn(null);

        $provider = new ConfigProvider($scopeConfig, $checkoutSession);
        $config = $provider->getConfig();

        $this->assertArrayHasKey('shipping', $config);
        $this->assertArrayHasKey('dhlparcel', $config['shipping']);

        $dhlConfig = $config['shipping']['dhlparcel'];
        $this->assertEquals('https://parcelshop.dhl.pl/mapa', $dhlConfig['mapUrl']);
        $this->assertEquals('pop', $dhlConfig['pointMapType']);
        $this->assertEquals('map', $dhlConfig['deliveryMode']);
        $this->assertTrue($dhlConfig['debug']);
        $this->assertNull($dhlConfig['selectedPoint']);
        $this->assertContains('dhlparcel_economy', $dhlConfig['pointMethods']);
        $this->assertContains('dhlparcel_connect', $dhlConfig['pointMethods']);
    }

    public function testGetConfigReturnsQuoteSelectedPointWhenPresent(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('all');
        $scopeConfig->method('isSetFlag')->willReturn(false);
        $shippingAddress = $this->createStub(QuoteAddress::class);
        $shippingAddress->method('getData')->willReturnCallback(
            static fn (string $key = '', mixed $index = null) => match ($key) {
                'dhl_service_point_id' => 'POP-123',
                'dhl_service_point_name' => 'DHL Point 123',
                'dhl_service_point_street' => 'Main Street 10',
                'dhl_service_point_postcode' => '00-001',
                'dhl_service_point_city' => 'Warsaw',
                default => null,
            }
        );

        $quote = $this->createStub(Quote::class);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);

        $checkoutSession = $this->createStub(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $provider = new ConfigProvider($scopeConfig, $checkoutSession);
        $config = $provider->getConfig();

        $selectedPoint = $config['shipping']['dhlparcel']['selectedPoint'];
        $this->assertIsArray($selectedPoint);
        $this->assertEquals('POP-123', $selectedPoint['sap']);
        $this->assertEquals('DHL Point 123', $selectedPoint['name']);
        $this->assertEquals('Main Street 10', $selectedPoint['street']);
        $this->assertEquals('00-001', $selectedPoint['zip']);
        $this->assertEquals('Warsaw', $selectedPoint['city']);
    }
}
