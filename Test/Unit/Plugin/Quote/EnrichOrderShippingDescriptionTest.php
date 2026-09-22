<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Quote;

use Ideo\Dhl\Model\ServicePoint\ServicePointDescriptionFormatter;
use Ideo\Dhl\Model\ServicePoint\ServicePointValidator;
use Ideo\Dhl\Plugin\Quote\EnrichOrderShippingDescription;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\ToOrder;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class EnrichOrderShippingDescriptionTest extends TestCase
{
    /**
     * @var ServicePointValidator
     */
    private $validator;

    /**
     * @var ServicePointDescriptionFormatter
     */
    private $formatter;

    /**
     * @var EnrichOrderShippingDescription
     */
    private $plugin;

    protected function setUp(): void
    {
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->validator = new ServicePointValidator($scopeConfigStub);
        $this->formatter = new ServicePointDescriptionFormatter();
        $this->plugin = new EnrichOrderShippingDescription($this->validator, $this->formatter);
    }

    public function testAfterConvertEnrichesShippingDescriptionForDhlServicePoint(): void
    {
        $toOrderStub = $this->createStub(ToOrder::class);
        $orderMock = $this->createMock(Order::class);
        $quoteAddressStub = $this->createStub(QuoteAddress::class);

        $quoteAddressStub->method('getData')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'shipping_method' => 'dhlparcel_economy_all',
                'dhl_service_point_id' => 'POP-777',
                'dhl_service_point_name' => 'Żabka Test',
                'dhl_service_point_street' => 'Kwiatowa 10',
                'dhl_service_point_postcode' => '00-999',
                'dhl_service_point_city' => 'Warszawa',
                default => null,
            }
        );

        $desc = 'DHL Parcel - DHL Parcel Economy - Points and Lockers';
        $orderMock->method('getShippingDescription')->willReturn($desc);

        $orderMock->expects($this->once())
            ->method('setShippingDescription')
            ->with($this->callback(static function (string $desc) {
                return str_contains($desc, 'DHL Parcel - DHL Parcel Economy - Points and Lockers')
                    && str_contains($desc, 'POP-777')
                    && str_contains($desc, 'Żabka Test');
            }));

        $result = $this->plugin->afterConvert($toOrderStub, $orderMock, $quoteAddressStub);

        $this->assertSame($orderMock, $result);
    }

    public function testAfterConvertLeavesDescriptionUntouchedForStandardCourierDelivery(): void
    {
        $toOrderStub = $this->createStub(ToOrder::class);
        $orderMock = $this->createMock(Order::class);
        $quoteAddressStub = $this->createStub(QuoteAddress::class);

        $quoteAddressStub->method('getData')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'shipping_method' => 'dhlparcel_courier',
                default => null,
            }
        );

        $orderMock->expects($this->never())->method('setShippingDescription');

        $result = $this->plugin->afterConvert($toOrderStub, $orderMock, $quoteAddressStub);

        $this->assertSame($orderMock, $result);
    }

    public function testAfterConvertLeavesDescriptionUntouchedForConnectMethod(): void
    {
        $toOrderStub = $this->createStub(ToOrder::class);
        $orderMock = $this->createMock(Order::class);
        $quoteAddressStub = $this->createStub(QuoteAddress::class);

        $quoteAddressStub->method('getData')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'shipping_method' => 'dhlparcel_connect',
                default => null,
            }
        );

        $orderMock->expects($this->never())->method('setShippingDescription');

        $result = $this->plugin->afterConvert($toOrderStub, $orderMock, $quoteAddressStub);

        $this->assertSame($orderMock, $result);
    }
}
