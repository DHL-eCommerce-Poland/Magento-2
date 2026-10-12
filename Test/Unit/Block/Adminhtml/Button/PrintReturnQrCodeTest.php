<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Block\Adminhtml\Button;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Block\Adminhtml\Button\PrintReturnQrCode;
use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class PrintReturnQrCodeTest extends TestCase
{
    public function testGetButtonDataReturnsConfigWhenReturnShipmentIdExists(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getReturnShipmentId')->willReturn('RET123456');
        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getEntityId')->willReturn(10);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')
            ->with('dhlpoland/shipment/printReturnQrCode', ['entity_id' => 10])
            ->willReturn('http://example.com/dhl/shipment/printReturnQrCode/entity_id/10');

        $button = $this->createButton($shipmentData, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEquals('Print Return QR Code', (string)$data['label']);
        $this->assertStringContainsString('printReturnQrCode', $data['on_click']);
    }

    public function testGetButtonDataReturnsEmptyWhenNoReturnShipmentId(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getReturnShipmentId')->willReturn(null);
        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getEntityId')->willReturn(10);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $urlBuilder = $this->createStub(UrlInterface::class);

        $button = $this->createButton($shipmentData, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEmpty($data);
    }

    private function createButton(AdminhtmlShipmentData $shipmentData, UrlInterface $urlBuilder): PrintReturnQrCode
    {
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeJs')->willReturnCallback(static fn (string $value): string => addslashes($value));

        return new PrintReturnQrCode($context, $escaper, $shipmentData);
    }
}
