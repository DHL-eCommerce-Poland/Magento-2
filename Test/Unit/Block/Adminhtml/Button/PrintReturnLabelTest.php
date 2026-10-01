<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Block\Adminhtml\Button\PrintReturnLabel;
use Ideo\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class PrintReturnLabelTest extends TestCase
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
            ->with('dhlpoland/shipment/printReturnLabel', ['entity_id' => 10])
            ->willReturn("http://example.com/printReturnLabel');alert(1);//");

        $button = $this->createButton($shipmentData, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEquals('Print Return Label', (string)$data['label']);
        $this->assertStringContainsString('printReturnLabel', $data['on_click']);
        $this->assertStringNotContainsString("printReturnLabel');alert(1)", $data['on_click']);
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

    private function createButton(AdminhtmlShipmentData $shipmentData, UrlInterface $urlBuilder): PrintReturnLabel
    {
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeJs')->willReturnCallback(static fn (string $value): string => addslashes($value));

        return new PrintReturnLabel($context, $escaper, $shipmentData);
    }
}
