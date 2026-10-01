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
        $scopeConfig->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlpoland/method_economy_all_point_delivery_mode', ScopeInterface::SCOPE_STORE)
            ->willReturn('map');

        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('carriers/dhlpoland/debug', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $checkoutSession = $this->createMock(CheckoutSession::class);
        $checkoutSession->expects(self::once())
            ->method('getQuote')
            ->willReturn(null);

        $assetRepository = $this->createMock(AssetRepository::class);
        $assetRepository->expects(self::exactly(11))
            ->method('getUrl')
            ->willReturnCallback(
                static fn (string $fileId) => match ($fileId) {
                    'Ideo_Dhl::images/logo_dhl_box_pop.png' => 'https://example.com/logo_dhl_box_pop.png',
                    'Ideo_Dhl::images/logo_dhl_pop.png' => 'https://example.com/logo_dhl_pop.png',
                    'Ideo_Dhl::images/logo_dhl_box.png' => 'https://example.com/logo_dhl_box.png',
                    'Ideo_Dhl::images/logo_dhl.png' => 'https://example.com/logo_dhl.png',
                    default => '',
                }
            );

        $provider = new ConfigProvider($scopeConfig, $checkoutSession, $assetRepository);
        $config = $provider->getConfig();

        $this->assertArrayHasKey('shipping', $config);
        $this->assertArrayHasKey('dhlpoland', $config['shipping']);

        $dhlConfig = $config['shipping']['dhlpoland'];
        $this->assertEquals('https://parcelshop.dhl.pl/mapa', $dhlConfig['mapUrl']);
        $this->assertEquals('all', $dhlConfig['pointMapType']);
        $this->assertEquals('map', $dhlConfig['deliveryMode']);
        $this->assertTrue($dhlConfig['debug']);
        $this->assertNull($dhlConfig['selectedPoint']);
        $this->assertEquals('https://example.com/logo_dhl_box_pop.png', $dhlConfig['logos']['dhlpoland_economy_all']);
        $this->assertEquals('https://example.com/logo_dhl_pop.png', $dhlConfig['logos']['dhlpoland_economy_pop']);
        $this->assertEquals('https://example.com/logo_dhl_box.png', $dhlConfig['logos']['dhlpoland_economy_box']);
        $this->assertEquals('https://example.com/logo_dhl.png', $dhlConfig['logos']['dhlpoland_connect_parcelshop']);
        $this->assertEquals('https://example.com/logo_dhl.png', $dhlConfig['logos']['dhlpoland_connect_packstation']);
        $this->assertEquals('https://example.com/logo_dhl.png', $dhlConfig['logos']['default']);
        $this->assertContainsEquals('dhlpoland_economy_all', $dhlConfig['pointMethods']);
        $this->assertContainsEquals('dhlpoland_economy_pop', $dhlConfig['pointMethods']);
        $this->assertContainsEquals('dhlpoland_economy_box', $dhlConfig['pointMethods']);
        $this->assertNotContains('dhlpoland_connect', $dhlConfig['pointMethods']);
        $this->assertContainsEquals('dhlpoland_connect_parcelshop', $dhlConfig['pointMethods']);
        $this->assertContainsEquals('dhlpoland_connect_packstation', $dhlConfig['pointMethods']);
        $this->assertEquals('all', $dhlConfig['pointMapTypes']['economy_all']);
        $this->assertEquals('all', $dhlConfig['pointMapTypes']['dhlpoland_economy_all']);
        $this->assertEquals('pop', $dhlConfig['pointMapTypes']['economy_pop']);
        $this->assertEquals('pop', $dhlConfig['pointMapTypes']['dhlpoland_economy_pop']);
        $this->assertEquals('box', $dhlConfig['pointMapTypes']['economy_box']);
        $this->assertEquals('box', $dhlConfig['pointMapTypes']['dhlpoland_economy_box']);
        $this->assertEquals('pop', $dhlConfig['pointMapTypes']['connect_parcelshop']);
        $this->assertEquals('pop', $dhlConfig['pointMapTypes']['dhlpoland_connect_parcelshop']);
        $this->assertEquals('box', $dhlConfig['pointMapTypes']['connect_packstation']);
        $this->assertEquals('box', $dhlConfig['pointMapTypes']['dhlpoland_connect_packstation']);
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

        $selectedPoint = $config['shipping']['dhlpoland']['selectedPoint'];
        $this->assertIsArray($selectedPoint);
        $this->assertEquals('POP-123', $selectedPoint['sap']);
        $this->assertEquals('DHL Point 123', $selectedPoint['name']);
        $this->assertEquals('Main Street 10', $selectedPoint['street']);
        $this->assertEquals('00-001', $selectedPoint['zip']);
        $this->assertEquals('Warsaw', $selectedPoint['city']);
    }
}
