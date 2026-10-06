<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Block\Adminhtml\Button\RecreateShipment;
use Ideo\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class RecreateShipmentTest extends TestCase
{
    /**
     * @var Stub
     */
    private $contextStub;
    /**
     * @var Stub
     */
    private $shipmentDataStub;
    /**
     * @var RecreateShipment
     */
    private $button;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->shipmentDataStub = $this->createStub(AdminhtmlShipmentData::class);

        $urlBuilderStub = $this->createStub(\Magento\Framework\UrlInterface::class);
        $urlBuilderStub->method('getUrl')->willReturn('http://example.com/dhlpoland/shipment/save');
        $this->contextStub->method('getUrlBuilder')->willReturn($urlBuilderStub);

        $escaperStub = $this->createStub(Escaper::class);
        $escaperStub->method('escapeJs')->willReturnCallback(fn (string $str) => addslashes($str));
        $this->contextStub->method('getEscaper')->willReturn($escaperStub);

        $this->button = new RecreateShipment(
            $this->contextStub,
            $escaperStub,
            $this->shipmentDataStub
        );
    }

    public function testGetButtonDataReturnsEmptyWhenDispatchNumberExists(): void
    {
        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getDispatchNumber')
            ->willReturn('DISPATCH123');

        $this->shipmentDataStub->method('getShipment')->willReturn($shipmentMock);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsEmptyWhenShipmentIsUnavailable(): void
    {
        $this->shipmentDataStub->method('getShipment')->willReturn(null);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsConfigForValidShipmentWithoutDispatchNumber(): void
    {
        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getDispatchNumber')
            ->willReturn(null);

        $this->shipmentDataStub->method('getShipment')->willReturn($shipmentMock);

        $buttonData = $this->button->getButtonData();

        $this->assertNotEmpty($buttonData);
        $this->assertEquals(__('Save & Resend to DHL'), $buttonData['label']);
        $this->assertEquals('save primary', $buttonData['class']);
        $this->assertEquals(70, $buttonData['sort_order']);
        $this->assertStringContainsString('Ideo_Dhl/js/shipment/recreate-shipment', $buttonData['on_click']);
    }
}
