<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Checkout;

use Ideo\Dhl\Model\Checkout\ConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testGetConfigReturnsConfiguredValues(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::atLeast(2))
            ->method('getValue')
            ->willReturnCallback(
                static fn (string $path) => match ($path) {
                    'carriers/dhlparcel/method_economy_point_map_type' => 'pop',
                    'carriers/dhlparcel/method_economy_point_delivery_mode' => 'map',
                    default => null,
                }
            );

        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('carriers/dhlparcel/debug', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->expects(self::once())
            ->method('getQuote')
            ->willReturn(null);

        $assetRepository = $this->createMock(AssetRepository::class);
        $assetRepository->expects(self::exactly(4))
            ->method('getUrl')
            ->willReturnCallback(
                static fn (string $fileId) => match ($fileId) {
                    'Ideo_Dhl::images/logo_dhl_box_pop.png' => 'https://example.com/logo_dhl_box_pop.png',
                    'Ideo_Dhl::images/logo_dhl.png' => 'https://example.com/logo_dhl.png',
                    default => '',
                }
            );

        $provider = new ConfigProvider($scopeConfig, $checkoutSession, $assetRepository);
        $config = $provider->getConfig();

        $this->assertArrayHasKey('shipping', $config);
        $this->assertArrayHasKey('dhlparcel', $config['shipping']);

        $dhlConfig = $config['shipping']['dhlparcel'];
        $this->assertEquals('https://parcelshop.dhl.pl/mapa', $dhlConfig['mapUrl']);
        $this->assertEquals('pop', $dhlConfig['pointMapType']);
        $this->assertEquals('map', $dhlConfig['deliveryMode']);
        $this->assertTrue($dhlConfig['debug']);
        $this->assertNull($dhlConfig['selectedPoint']);
        $this->assertEquals('https://example.com/logo_dhl_box_pop.png', $dhlConfig['logos']['dhlparcel_economy']);
        $this->assertEquals('https://example.com/logo_dhl.png', $dhlConfig['logos']['dhlparcel_connect_parcel_shop']);
        $this->assertEquals('https://example.com/logo_dhl.png', $dhlConfig['logos']['dhlparcel_connect_packstation']);
        $this->assertEquals('https://example.com/logo_dhl.png', $dhlConfig['logos']['default']);
        $this->assertContainsEquals('dhlparcel_economy', $dhlConfig['pointMethods']);
        $this->assertNotContains('dhlparcel_connect', $dhlConfig['pointMethods']);
        $this->assertContainsEquals('dhlparcel_connect_parcel_shop', $dhlConfig['pointMethods']);
        $this->assertContainsEquals('dhlparcel_connect_packstation', $dhlConfig['pointMethods']);
        $this->assertEquals('pop', $dhlConfig['pointMapTypes']['connect_parcel_shop']);
        $this->assertEquals('pop', $dhlConfig['pointMapTypes']['dhlparcel_connect_parcel_shop']);
        $this->assertEquals('box', $dhlConfig['pointMapTypes']['connect_packstation']);
        $this->assertEquals('box', $dhlConfig['pointMapTypes']['dhlparcel_connect_packstation']);
    }

    public function testGetConfigReturnsQuoteSelectedPointWhenPresent(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('all');
        $scopeConfig->method('isSetFlag')->willReturn(false);
        $shippingAddress = $this->createStub(QuoteAddress::class);
        $shippingAddress->method('getData')->willReturnCallback(
            static fn (string $key = '') => match ($key) {
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

        $assetRepository = $this->createStub(AssetRepository::class);
        $assetRepository->method('getUrl')->willReturn('https://example.com/logo.png');

        $provider = new ConfigProvider($scopeConfig, $checkoutSession, $assetRepository);
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
