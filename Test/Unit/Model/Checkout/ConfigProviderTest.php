<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Checkout;

use Ideo\Dhl\Model\Checkout\ConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
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

        $provider = new ConfigProvider($scopeConfig);
        $config = $provider->getConfig();

        $this->assertArrayHasKey('shipping', $config);
        $this->assertArrayHasKey('dhlparcel', $config['shipping']);

        $dhlConfig = $config['shipping']['dhlparcel'];
        $this->assertEquals('https://parcelshop.dhl.pl/mapa', $dhlConfig['mapUrl']);
        $this->assertEquals('pop', $dhlConfig['pointMapType']);
        $this->assertEquals('map', $dhlConfig['deliveryMode']);
        $this->assertContains('dhlparcel_economy', $dhlConfig['pointMethods']);
        $this->assertContains('dhlparcel_connect', $dhlConfig['pointMethods']);
    }
}
