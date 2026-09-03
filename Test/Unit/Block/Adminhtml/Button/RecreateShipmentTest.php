<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Block\Adminhtml\Button\RecreateShipment;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RecreateShipmentTest extends TestCase
{
    private Context|Stub $contextStub;
    private RequestInterface|MockObject $requestMock;
    private ShipmentDataRepositoryInterface|Stub $shipmentRepositoryStub;
    private RecreateShipment $button;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->shipmentRepositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);

        $escaperStub = $this->createStub(Escaper::class);
        $escaperStub->method('escapeJs')->willReturnCallback(fn(string $str) => addslashes($str));
        $this->contextStub->method('getEscaper')->willReturn($escaperStub);

        $this->contextStub->method('getRequest')
            ->willReturn($this->requestMock);

        $this->button = new RecreateShipment(
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

    public function testGetButtonDataReturnsEmptyWhenDispatchNumberExists(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(10);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getDispatchNumber')
            ->willReturn('DISPATCH123');

        $this->shipmentRepositoryStub->method('getById')
            ->willReturn($shipmentMock);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsEmptyOnRepositoryException(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(10);

        $this->shipmentRepositoryStub->method('getById')
            ->willThrowException(new RuntimeException('Entity not found'));

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsConfigForValidShipmentWithoutDispatchNumber(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn(10);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getDispatchNumber')
            ->willReturn(null);

        $this->shipmentRepositoryStub->method('getById')
            ->willReturn($shipmentMock);

        $buttonData = $this->button->getButtonData();

        $this->assertNotEmpty($buttonData);
        $this->assertEquals(__('Save & Resend to DHL'), $buttonData['label']);
        $this->assertEquals('save primary', $buttonData['class']);
        $this->assertEquals(70, $buttonData['sort_order']);
        $this->assertStringContainsString('dhlparcel_shipment_form', $buttonData['on_click']);
    }
}
