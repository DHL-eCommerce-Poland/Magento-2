<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Plugin\Quote;

use DhlPoland\Dhl\Plugin\Quote\CopyServicePointToOrderAddress;
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
                    'dhl_service_point_name' => 'Packstation 101',
                    'dhl_service_point_street' => 'Keetstr. 19',
                    'dhl_service_point_postcode' => '49393',
                    'dhl_service_point_city' => 'Lohne',
                    'dhl_is_packstation' => 1,
                    'dhl_is_postfiliale' => 0,
                    'dhl_postnummer' => '987654321',
                    default => null,
                };
            });

        $savedData = [];
        $orderAddressMock->expects($this->exactly(8))
            ->method('setData')
            ->willReturnCallback(function ($key, $value) use ($orderAddressMock, &$savedData) {
                $savedData[$key] = $value;
                return $orderAddressMock;
            });

        $result = $plugin->afterConvert($subjectStub, $orderAddressMock, $quoteAddressStub);

        $this->assertSame($orderAddressMock, $result);
        $this->assertEquals('SAP123', $savedData['dhl_service_point_id']);
        $this->assertEquals('Packstation 101', $savedData['dhl_service_point_name']);
        $this->assertEquals('Keetstr. 19', $savedData['dhl_service_point_street']);
        $this->assertEquals('49393', $savedData['dhl_service_point_postcode']);
        $this->assertEquals('Lohne', $savedData['dhl_service_point_city']);
        $this->assertEquals(1, $savedData['dhl_is_packstation']);
        $this->assertEquals(0, $savedData['dhl_is_postfiliale']);
        $this->assertEquals('987654321', $savedData['dhl_postnummer']);
    }
}
