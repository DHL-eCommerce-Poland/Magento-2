<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Block\Adminhtml\Button\DownloadEpod;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DownloadEpodTest extends TestCase
{
    private RequestInterface|Stub $requestStub;
    private ShipmentDataRepositoryInterface|Stub $repositoryStub;
    private UrlInterface|Stub $urlBuilderStub;
    private DownloadEpod $button;

    protected function setUp(): void
    {
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->repositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);
        $this->urlBuilderStub = $this->createStub(UrlInterface::class);

        $this->button = new DownloadEpod(
            $this->requestStub,
            $this->repositoryStub,
            $this->urlBuilderStub
        );
    }

    public function testGetButtonDataReturnsEmptyWhenNoEntityId(): void
    {
        $this->requestStub->method('getParam')->willReturn(0);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsEmptyWhenNoShipmentId(): void
    {
        $this->requestStub->method('getParam')->willReturn(10);
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn(null);
        $this->repositoryStub->method('getById')->willReturn($shipment);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataSuccess(): void
    {
        $this->requestStub->method('getParam')->willReturn(10);
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $this->repositoryStub->method('getById')->willReturn($shipment);
        $this->urlBuilderStub->method('getUrl')->willReturn('http://example.com/downloadEpod');

        $data = $this->button->getButtonData();

        $this->assertNotEmpty($data);
        $this->assertEquals('Download ePOD', (string) $data['label']);
        $this->assertStringContainsString('window.open(\'http://example.com/downloadEpod\'', $data['on_click']);
    }
}
