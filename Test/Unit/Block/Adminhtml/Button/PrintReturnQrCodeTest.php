<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Block\Adminhtml\Button\PrintReturnQrCode;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class PrintReturnQrCodeTest extends TestCase
{
    public function testGetButtonDataReturnsConfigWhenReturnShipmentIdExists(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('entity_id')->willReturn(10);

        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getReturnShipmentId')->willReturn('RET123456');

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->method('getById')->with(10)->willReturn($shipment);

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')
            ->with('ideo_dhl/shipment/printReturnQrCode', ['entity_id' => 10])
            ->willReturn('http://example.com/dhl/shipment/printReturnQrCode/entity_id/10');

        $button = new PrintReturnQrCode($request, $repository, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEquals('Print Return QR Code', (string)$data['label']);
        $this->assertStringContainsString('printReturnQrCode', $data['on_click']);
    }

    public function testGetButtonDataReturnsEmptyWhenNoReturnShipmentId(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('entity_id')->willReturn(10);

        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getReturnShipmentId')->willReturn(null);

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->method('getById')->with(10)->willReturn($shipment);

        $urlBuilder = $this->createStub(UrlInterface::class);

        $button = new PrintReturnQrCode($request, $repository, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEmpty($data);
    }
}
