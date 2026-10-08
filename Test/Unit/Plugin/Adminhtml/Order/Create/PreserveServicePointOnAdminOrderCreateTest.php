<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Adminhtml\Order\Create;

use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Plugin\Adminhtml\Order\Create\PreserveServicePointOnAdminOrderCreate;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\AdminOrder\Create as AdminOrderCreate;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PreserveServicePointOnAdminOrderCreateTest extends TestCase
{
    /**
     * @var MethodCatalog|Stub
     */
    private MethodCatalog|Stub $methodCatalogStub;

    protected function setUp(): void
    {
        $this->methodCatalogStub = $this->createStub(MethodCatalog::class);
        $this->methodCatalogStub->method('isServicePointMethod')->willReturn(true);
    }

    public function testBeforeImportPostDataMergesServicePointDataIntoShippingAddressArray(): void
    {
        $plugin = new PreserveServicePointOnAdminOrderCreate($this->methodCatalogStub);
        $adminOrderCreateStub = $this->createStub(AdminOrderCreate::class);

        $postData = [
            'dhl_service_point_id' => 'SAP999',
            'dhl_service_point_name' => 'Żabka Test',
            'dhl_service_point_street' => 'ul. Główna 1',
            'dhl_service_point_postcode' => '00-001',
            'dhl_service_point_city' => 'Warszawa',
            'dhl_is_packstation' => 0,
            'dhl_is_postfiliale' => 1,
            'dhl_postnummer' => '123456789',
            'shipping_address' => [
                'firstname' => 'Jan',
                'lastname' => 'Kowalski',
            ],
        ];

        $result = $plugin->beforeImportPostData($adminOrderCreateStub, $postData);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $mergedData = $result[0];

        $this->assertEquals('SAP999', $mergedData['shipping_address']['dhl_service_point_id']);
        $this->assertEquals('Żabka Test', $mergedData['shipping_address']['dhl_service_point_name']);
        $this->assertEquals('ul. Główna 1', $mergedData['shipping_address']['dhl_service_point_street']);
        $this->assertEquals('00-001', $mergedData['shipping_address']['dhl_service_point_postcode']);
        $this->assertEquals('Warszawa', $mergedData['shipping_address']['dhl_service_point_city']);
        $this->assertEquals(0, $mergedData['shipping_address']['dhl_is_packstation']);
        $this->assertEquals(1, $mergedData['shipping_address']['dhl_is_postfiliale']);
        $this->assertEquals('123456789', $mergedData['shipping_address']['dhl_postnummer']);
    }

    public function testAfterInitFromOrderCopiesServicePointDataFromOrderToQuoteAndSaves(): void
    {
        $plugin = new PreserveServicePointOnAdminOrderCreate($this->methodCatalogStub);

        $adminOrderCreateStub = $this->createStub(AdminOrderCreate::class);
        $orderStub = $this->createStub(Order::class);
        $orderAddressStub = $this->createStub(OrderAddress::class);
        $quoteStub = $this->createStub(Quote::class);
        $quoteAddressMock = $this->createMock(QuoteAddress::class);

        $adminOrderCreateStub->method('getQuote')->willReturn($quoteStub);
        $quoteStub->method('getShippingAddress')->willReturn($quoteAddressMock);
        $orderStub->method('getShippingAddress')->willReturn($orderAddressStub);

        $quoteAddressMock->method('getId')->willReturn(123);

        $orderAddressStub->method('getData')
            ->willReturnCallback(function (string $key) {
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

        $setDataCalls = [];
        $quoteAddressMock->expects($this->exactly(8))
            ->method('setData')
            ->willReturnCallback(function (string $key, mixed $value) use ($quoteAddressMock, &$setDataCalls) {
                $setDataCalls[$key] = $value;
                return $quoteAddressMock;
            });

        $quoteAddressMock->expects($this->once())
            ->method('save')
            ->willReturnSelf();

        $result = $plugin->afterInitFromOrder($adminOrderCreateStub, $adminOrderCreateStub, $orderStub);

        $this->assertSame($adminOrderCreateStub, $result);
        $this->assertEquals('SAP123', $setDataCalls['dhl_service_point_id']);
        $this->assertEquals('Packstation 101', $setDataCalls['dhl_service_point_name']);
        $this->assertEquals('Keetstr. 19', $setDataCalls['dhl_service_point_street']);
        $this->assertEquals('49393', $setDataCalls['dhl_service_point_postcode']);
        $this->assertEquals('Lohne', $setDataCalls['dhl_service_point_city']);
        $this->assertEquals(1, $setDataCalls['dhl_is_packstation']);
        $this->assertEquals(0, $setDataCalls['dhl_is_postfiliale']);
        $this->assertEquals('987654321', $setDataCalls['dhl_postnummer']);
    }

    public function testAroundSetShippingAddressPreservesServicePointDataWhenNewAddressLacksIt(): void
    {
        $plugin = new PreserveServicePointOnAdminOrderCreate($this->methodCatalogStub);

        $oldAddressStub = $this->createStub(QuoteAddress::class);
        $newAddressMock = $this->createMock(QuoteAddress::class);
        $quoteStub = $this->createStub(Quote::class);
        $adminOrderCreateStub = $this->createStub(AdminOrderCreate::class);

        $adminOrderCreateStub->method('getQuote')->willReturn($quoteStub);
        $newAddressMock->method('getShippingMethod')->willReturn('dhlpoland_economy_pop');

        $quoteStub->method('getShippingAddress')
            ->willReturnOnConsecutiveCalls($oldAddressStub, $newAddressMock, $newAddressMock, $newAddressMock);

        $oldAddressStub->method('getData')
            ->willReturnCallback(function (string $key) {
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

        $newAddressMock->method('getData')
            ->willReturnCallback(function (string $key) {
                return null;
            });

        $setDataCalls = [];
        $newAddressMock->expects($this->atLeast(8))
            ->method('setData')
            ->willReturnCallback(function (string $key, mixed $value) use ($newAddressMock, &$setDataCalls) {
                $setDataCalls[$key] = $value;
                return $newAddressMock;
            });

        $proceed = function ($addr) use ($adminOrderCreateStub) {
            return $adminOrderCreateStub;
        };

        $result = $plugin->aroundSetShippingAddress($adminOrderCreateStub, $proceed, []);

        $this->assertSame($adminOrderCreateStub, $result);
        $this->assertEquals('SAP123', $setDataCalls['dhl_service_point_id']);
        $this->assertEquals('Packstation 101', $setDataCalls['dhl_service_point_name']);
        $this->assertEquals('Keetstr. 19', $setDataCalls['dhl_service_point_street']);
        $this->assertEquals('49393', $setDataCalls['dhl_service_point_postcode']);
        $this->assertEquals('Lohne', $setDataCalls['dhl_service_point_city']);
        $this->assertEquals(1, $setDataCalls['dhl_is_packstation']);
        $this->assertEquals(0, $setDataCalls['dhl_is_postfiliale']);
        $this->assertEquals('987654321', $setDataCalls['dhl_postnummer']);
    }

    public function testAroundSetShippingAddressAppliesNewPointDataFromPostArray(): void
    {
        $plugin = new PreserveServicePointOnAdminOrderCreate($this->methodCatalogStub);

        $oldAddressStub = $this->createStub(QuoteAddress::class);
        $newAddressMock = $this->createMock(QuoteAddress::class);
        $quoteStub = $this->createStub(Quote::class);
        $adminOrderCreateStub = $this->createStub(AdminOrderCreate::class);

        $adminOrderCreateStub->method('getQuote')->willReturn($quoteStub);
        $newAddressMock->method('getShippingMethod')->willReturn('dhlpoland_economy_pop');

        $quoteStub->method('getShippingAddress')
            ->willReturnOnConsecutiveCalls($oldAddressStub, $newAddressMock, $newAddressMock, $newAddressMock);

        $newAddressInput = [
            'dhl_service_point_id' => 'NEW_SAP_777',
            'dhl_service_point_name' => 'New POP',
            'dhl_service_point_street' => 'Prosta 2',
            'dhl_service_point_postcode' => '00-002',
            'dhl_service_point_city' => 'Kraków',
            'dhl_is_packstation' => 0,
            'dhl_is_postfiliale' => 1,
            'dhl_postnummer' => null,
        ];

        $setDataCalls = [];
        $newAddressMock->expects($this->atLeast(8))
            ->method('setData')
            ->willReturnCallback(function (string $key, mixed $value) use ($newAddressMock, &$setDataCalls) {
                $setDataCalls[$key] = $value;
                return $newAddressMock;
            });

        $newAddressMock->method('getId')->willReturn(123);

        $proceed = function ($addr) use ($adminOrderCreateStub) {
            return $adminOrderCreateStub;
        };

        $result = $plugin->aroundSetShippingAddress($adminOrderCreateStub, $proceed, $newAddressInput);

        $this->assertSame($adminOrderCreateStub, $result);
        $this->assertEquals('NEW_SAP_777', $setDataCalls['dhl_service_point_id']);
        $this->assertEquals('New POP', $setDataCalls['dhl_service_point_name']);
    }

    public function testBeforeCreateOrderSavesQuoteAddressWhenServicePointIsPresent(): void
    {
        $plugin = new PreserveServicePointOnAdminOrderCreate($this->methodCatalogStub);

        $adminOrderCreateStub = $this->createStub(AdminOrderCreate::class);
        $quoteStub = $this->createStub(Quote::class);
        $quoteAddressMock = $this->createMock(QuoteAddress::class);

        $adminOrderCreateStub->method('getQuote')->willReturn($quoteStub);
        $quoteStub->method('getShippingAddress')->willReturn($quoteAddressMock);

        $quoteAddressMock->method('getShippingMethod')->willReturn('dhlpoland_economy_pop');
        $quoteAddressMock->method('getId')->willReturn(123);
        $quoteAddressMock->expects($this->once())->method('save');

        $plugin->beforeCreateOrder($adminOrderCreateStub);
    }
}
