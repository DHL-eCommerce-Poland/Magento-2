<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Block\Adminhtml\Button\DownloadScan;
use Ideo\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DownloadScanTest extends TestCase
{
    /**
     * @var Stub
     */
    private $shipmentDataStub;
    /**
     * @var Stub
     */
    private $urlBuilderStub;
    /**
     * @var DownloadScan
     */
    private $button;

    protected function setUp(): void
    {
        $this->shipmentDataStub = $this->createStub(AdminhtmlShipmentData::class);
        $this->urlBuilderStub = $this->createStub(UrlInterface::class);
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilderStub);
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeJs')->willReturnCallback(static fn (string $value): string => addslashes($value));

        $this->button = new DownloadScan(
            $context,
            $escaper,
            $this->shipmentDataStub
        );
    }

    public function testGetButtonDataReturnsEmptyWhenNoEntityId(): void
    {
        $this->shipmentDataStub->method('getEntityId')->willReturn(null);
        $this->shipmentDataStub->method('getShipment')->willReturn(null);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsEmptyWhenNoShipmentId(): void
    {
        $this->shipmentDataStub->method('getEntityId')->willReturn(10);
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn(null);
        $this->shipmentDataStub->method('getShipment')->willReturn($shipment);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataSuccess(): void
    {
        $this->shipmentDataStub->method('getEntityId')->willReturn(10);
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $this->shipmentDataStub->method('getShipment')->willReturn($shipment);
        $this->urlBuilderStub->method('getUrl')->willReturn('http://example.com/downloadScan');

        $data = $this->button->getButtonData();

        $this->assertNotEmpty($data);
        $this->assertEquals('Download Scan', (string) $data['label']);
        $this->assertStringContainsString('download-document', $data['on_click']);
        $this->assertStringContainsString('http://example.com/downloadScan', $data['on_click']);
    }
}
