<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Block\Adminhtml\Button\UpdateStatusShipment;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UpdateStatusShipmentTest extends TestCase
{
    private Context|Stub $contextStub;
    private RequestInterface|MockObject $requestMock;
    private UrlInterface|Stub $urlBuilderStub;
    private ShipmentDataRepositoryInterface|Stub $shipmentRepositoryStub;
    private UpdateStatusShipment $button;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->urlBuilderStub = $this->createStub(UrlInterface::class);
        $this->shipmentRepositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);

        $this->contextStub->method('getRequest')
            ->willReturn($this->requestMock);

        $this->contextStub->method('getUrlBuilder')
            ->willReturn($this->urlBuilderStub);

        $this->button = new UpdateStatusShipment(
            $this->contextStub,
            $this->shipmentRepositoryStub
        );
    }

    public function testGetButtonDataReturnsEmptyWithoutEntityId(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(null);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsEmptyWhenNoShipmentId(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(10);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn(null);

        $this->shipmentRepositoryStub->method('getById')
            ->with(10)
            ->willReturn($shipmentMock);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsConfigWithValidShipmentId(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(10);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn('1234567890');

        $this->shipmentRepositoryStub->method('getById')
            ->with(10)
            ->willReturn($shipmentMock);

        $this->urlBuilderStub->method('getUrl')
            ->with('ideo_dhl/shipment/updateStatus', ['entity_id' => 10, 'redirect_to_edit' => 1])
            ->willReturn('http://magento.local/admin/ideo_dhl/shipment/updateStatus/entity_id/10');

        $buttonData = $this->button->getButtonData();

        $this->assertNotEmpty($buttonData);
        $this->assertEquals(__('Update Status'), $buttonData['label']);
        $this->assertStringContainsString('location.href', $buttonData['on_click']);
    }
}
