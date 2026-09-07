<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Block\Adminhtml\Button\UpdateStatusShipment;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UpdateStatusShipmentTest extends TestCase
{
    /**
     * @var Stub
     */
    private $contextStub;
    /**
     * @var MockObject
     */
    private $requestMock;
    /**
     * @var Stub
     */
    private $urlBuilderStub;
    /**
     * @var Stub
     */
    private $shipmentRepositoryStub;
    /**
     * @var UpdateStatusShipment
     */
    private $button;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->urlBuilderStub = $this->createStub(UrlInterface::class);
        $this->shipmentRepositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);

        $escaperStub = $this->createStub(Escaper::class);
        $escaperStub->method('escapeJs')->willReturnCallback(fn (string $str) => addslashes($str));
        $this->contextStub->method('getEscaper')->willReturn($escaperStub);

        $this->contextStub->method('getRequest')
            ->willReturn($this->requestMock);

        $this->contextStub->method('getUrlBuilder')
            ->willReturn($this->urlBuilderStub);

        $this->button = new UpdateStatusShipment(
            $this->contextStub,
            $escaperStub,
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
            ->willReturn($shipmentMock);

        $this->urlBuilderStub->method('getUrl')
            ->willReturn('http://magento.local/admin/dhlparcel/shipment/updateStatus/entity_id/10');

        $buttonData = $this->button->getButtonData();

        $this->assertNotEmpty($buttonData);
        $this->assertEquals(__('Update Status'), $buttonData['label']);
        $this->assertStringContainsString('location.href', $buttonData['on_click']);
    }
}
