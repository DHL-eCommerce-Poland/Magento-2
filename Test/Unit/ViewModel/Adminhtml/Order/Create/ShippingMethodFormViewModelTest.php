<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\ViewModel\Adminhtml\Order\Create;

use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\ViewModel\Adminhtml\Order\Create\ShippingMethodFormViewModel;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;

class ShippingMethodFormViewModelTest extends TestCase
{
    public function testIsDhlServicePointMethodReturnsFalseForNonDhlCarrier(): void
    {
        $methodCatalogStub = $this->createStub(MethodCatalog::class);
        $viewModel = new ShippingMethodFormViewModel($methodCatalogStub);

        $this->assertFalse($viewModel->isDhlServicePointMethod('inpost', 'courier', 'inpost_courier'));
    }

    public function testIsDhlServicePointMethodDelegatesToMethodCatalog(): void
    {
        $methodCatalogStub = $this->createStub(MethodCatalog::class);
        $methodCatalogStub->method('isServicePointMethod')
            ->willReturnCallback(fn(string $code) => $code === 'economy_pop');

        $viewModel = new ShippingMethodFormViewModel($methodCatalogStub);

        $this->assertTrue($viewModel->isDhlServicePointMethod('dhlpoland', 'dhlpoland_economy_pop', 'dhlpoland_economy_pop'));
        $this->assertFalse($viewModel->isDhlServicePointMethod('dhlpoland', 'parcel_polska', 'dhlpoland_parcel_polska'));
    }

    public function testGetServicePointDataReadsAndDecodesQuoteAddress(): void
    {
        $methodCatalogStub = $this->createStub(MethodCatalog::class);
        $viewModel = new ShippingMethodFormViewModel($methodCatalogStub);

        $addressStub = $this->createStub(Address::class);
        $addressStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'dhl_service_point_id' => 'POP123',
                'dhl_service_point_name' => 'Sn&auml;ck',
                'dhl_service_point_street' => 'Street 1',
                'dhl_service_point_postcode' => '00-001',
                'dhl_service_point_city' => 'City',
                'dhl_is_packstation' => 1,
                'dhl_is_postfiliale' => 0,
                'dhl_postnummer' => '999999',
                default => null,
            };
        });

        $this->assertSame('POP123', $viewModel->getServicePointId($addressStub));
        $this->assertSame('Snäck', $viewModel->getServicePointName($addressStub));
        $this->assertSame('Street 1', $viewModel->getServicePointStreet($addressStub));
        $this->assertSame('00-001', $viewModel->getServicePointPostcode($addressStub));
        $this->assertSame('City', $viewModel->getServicePointCity($addressStub));
        $this->assertSame(1, $viewModel->getIsPackstation($addressStub));
        $this->assertSame(0, $viewModel->getIsPostfiliale($addressStub));
        $this->assertSame('999999', $viewModel->getPostnummer($addressStub));
    }

    public function testGetFormattedDisplayTextReturnsFormattedString(): void
    {
        $methodCatalogStub = $this->createStub(MethodCatalog::class);
        $viewModel = new ShippingMethodFormViewModel($methodCatalogStub);

        $addressStub = $this->createStub(Address::class);
        $addressStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'dhl_service_point_id' => '4505130',
                'dhl_service_point_name' => 'DHL POP ŻABKA',
                'dhl_service_point_street' => 'Rynek 25/U2',
                'dhl_service_point_postcode' => '38-700',
                'dhl_service_point_city' => 'USTRZYKI DOLNE',
                default => null,
            };
        });

        $expected = '(4505130) DHL POP ŻABKA, Rynek 25/U2, 38-700 USTRZYKI DOLNE';
        $this->assertSame($expected, $viewModel->getFormattedDisplayText($addressStub));
    }

    public function testGetFormattedDisplayTextReturnsEmptyStringWhenIdIsEmpty(): void
    {
        $methodCatalogStub = $this->createStub(MethodCatalog::class);
        $viewModel = new ShippingMethodFormViewModel($methodCatalogStub);

        $this->assertSame('', $viewModel->getFormattedDisplayText(null));
    }

    public function testGetCountryIdReturnsCountryOrFallback(): void
    {
        $methodCatalogStub = $this->createStub(MethodCatalog::class);
        $viewModel = new ShippingMethodFormViewModel($methodCatalogStub);

        $this->assertSame('PL', $viewModel->getCountryId(null));

        $addressStub = $this->createStub(Address::class);
        $addressStub->method('getCountryId')->willReturn('DE');

        $this->assertSame('DE', $viewModel->getCountryId($addressStub));
    }
}
