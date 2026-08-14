<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Quote;

use Ideo\Dhl\Plugin\Quote\CopyServicePointToOrderAddress;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\ToOrderAddress;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\TestCase;

class CopyServicePointToOrderAddressTest extends TestCase
{
    public function testAfterConvertCopiesServicePointDataToOrderAddress(): void
    {
        $plugin = new CopyServicePointToOrderAddress();
        $subjectStub = $this->createStub(ToOrderAddress::class);
        $quoteAddressStub = $this->createStub(QuoteAddress::class);
        $orderAddressMock = $this->createMock(OrderAddress::class);

        $quoteAddressStub->method('getData')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'dhl_service_point_id' => 'SAP123',
                    'dhl_service_point_name' => 'Żabka Test',
                    'dhl_service_point_street' => 'Polna 1',
                    'dhl_service_point_postcode' => '00-111',
                    'dhl_service_point_city' => 'Warszawa',
                    default => null,
                };
            });

        $savedData = [];
        $orderAddressMock->expects($this->exactly(5))
            ->method('setData')
            ->willReturnCallback(function ($key, $value) use ($orderAddressMock, &$savedData) {
                $savedData[$key] = $value;
                return $orderAddressMock;
            });

        $result = $plugin->afterConvert($subjectStub, $orderAddressMock, $quoteAddressStub);

        $this->assertSame($orderAddressMock, $result);
        $this->assertEquals('SAP123', $savedData['dhl_service_point_id']);
        $this->assertEquals('Żabka Test', $savedData['dhl_service_point_name']);
        $this->assertEquals('Polna 1', $savedData['dhl_service_point_street']);
        $this->assertEquals('00-111', $savedData['dhl_service_point_postcode']);
        $this->assertEquals('Warszawa', $savedData['dhl_service_point_city']);
    }
}
