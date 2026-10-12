<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Block\Adminhtml\Button;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Block\Adminhtml\Button\CreateReturnShipment;
use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class CreateReturnShipmentTest extends TestCase
{
    public function testGetButtonDataReturnsConfigWhenShipmentIdExistsAndNoReturnShipmentId(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $shipment->method('getReturnShipmentId')->willReturn(null);

        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getEntityId')->willReturn(10);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')
            ->willReturn('http://example.com/createReturnShipment');

        $button = $this->createButton($shipmentData, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEquals('Create Return Shipment', (string) $data['label']);
        $this->assertStringContainsString('DhlPoland_Dhl/js/shipment/create-return', $data['on_click']);
        $this->assertStringContainsString('http://example.com/createReturnShipment', $data['on_click']);
        $this->assertEquals(65, $data['sort_order']);
    }

    public function testGetButtonDataReturnsEmptyWhenReturnShipmentIdAlreadyExists(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $shipment->method('getReturnShipmentId')->willReturn('RET123456');

        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getEntityId')->willReturn(10);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $urlBuilder = $this->createStub(UrlInterface::class);

        $button = $this->createButton($shipmentData, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEmpty($data);
    }

    public function testGetButtonDataReturnsEmptyWhenNoShipmentId(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn(null);

        $shipmentData = $this->createStub(AdminhtmlShipmentData::class);
        $shipmentData->method('getEntityId')->willReturn(10);
        $shipmentData->method('getShipment')->willReturn($shipment);

        $urlBuilder = $this->createStub(UrlInterface::class);

        $button = $this->createButton($shipmentData, $urlBuilder);
        $data = $button->getButtonData();

        $this->assertEmpty($data);
    }

    private function createButton(AdminhtmlShipmentData $shipmentData, UrlInterface $urlBuilder): CreateReturnShipment
    {
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeJs')->willReturnCallback(static fn (string $value): string => addslashes($value));

        return new CreateReturnShipment($context, $escaper, $shipmentData);
    }
}
