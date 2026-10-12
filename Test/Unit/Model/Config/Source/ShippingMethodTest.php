<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Config\Source;

use Ideo\Dhl\Model\Config\Source\ShippingMethod;
use Ideo\Dhl\Model\MethodCatalog;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ShippingMethodTest extends TestCase
{
    public function testToOptionArrayReturnsDhlShippingMethods(): void
    {
        $catalogMock = $this->createStub(MethodCatalog::class);
        $catalogMock->method('getMethods')->willReturn([
            ['code' => 'parcel_polska', 'title' => 'Kurier DHL'],
            ['code' => 'premium', 'title' => 'Kurier DHL Premium'],
        ]);

        $scopeConfigMock = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigMock->method('getValue')->willReturn(null);

        $source = new ShippingMethod($catalogMock, $scopeConfigMock);
        $options = $source->toOptionArray();

        self::assertCount(2, $options);
        self::assertSame('parcel_polska', $options[0]['value']);
        self::assertEquals('Kurier DHL [parcel_polska]', (string)$options[0]['label']);
    }

    public function testToOptionArrayUsesConfiguredCustomTitle(): void
    {
        $catalogMock = $this->createStub(MethodCatalog::class);
        $catalogMock->method('getMethods')->willReturn([
            ['code' => 'parcel_polska', 'title' => 'Kurier DHL'],
        ]);

        $scopeConfigMock = $this->createStub(ScopeConfigInterface::class);
        $scopeConfigMock->method('getValue')->willReturnCallback(
            static function (string $path) {
                if ($path === 'carriers/dhlpoland/method_parcel_polska_method_name') {
                    return 'Custom Courier Name';
                }
                return null;
            }
        );

        $source = new ShippingMethod($catalogMock, $scopeConfigMock);
        $options = $source->toOptionArray();

        self::assertCount(1, $options);
        self::assertEquals('Custom Courier Name [parcel_polska]', (string)$options[0]['label']);
    }
}
