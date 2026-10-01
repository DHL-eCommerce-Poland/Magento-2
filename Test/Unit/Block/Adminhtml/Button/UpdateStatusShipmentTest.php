<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Block\Adminhtml\Button\UpdateStatusShipment;
use Ideo\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
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
     * @var MockObject|Stub
     */
    private $urlBuilderStub;
    /**
     * @var Stub
     */
    private $shipmentDataStub;
    /**
     * @var UpdateStatusShipment
     */
    private $button;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->urlBuilderStub = $this->createStub(UrlInterface::class);
        $this->shipmentDataStub = $this->createStub(AdminhtmlShipmentData::class);

        $escaperStub = $this->createStub(Escaper::class);
        $escaperStub->method('escapeJs')->willReturnCallback(fn (string $str) => addslashes($str));
        $this->contextStub->method('getEscaper')->willReturn($escaperStub);

        $this->contextStub->method('getUrlBuilder')
            ->willReturnCallback(fn () => $this->urlBuilderStub);

        $this->button = new UpdateStatusShipment(
            $this->contextStub,
            $escaperStub,
            $this->shipmentDataStub,
            new Json()
        );
    }

    public function testGetButtonDataReturnsEmptyWithoutEntityId(): void
    {
        $this->shipmentDataStub->method('getEntityId')->willReturn(null);
        $this->shipmentDataStub->method('getShipment')->willReturn(null);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsEmptyWhenNoShipmentId(): void
    {
        $this->shipmentDataStub->method('getEntityId')->willReturn(10);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn(null);

        $this->shipmentDataStub->method('getShipment')->willReturn($shipmentMock);

        $this->assertEmpty($this->button->getButtonData());
    }

    public function testGetButtonDataReturnsConfigWithValidShipmentId(): void
    {
        $this->shipmentDataStub->method('getEntityId')->willReturn(10);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn('1234567890');

        $this->shipmentDataStub->method('getShipment')->willReturn($shipmentMock);

        $this->urlBuilderStub = $this->createMock(UrlInterface::class);
        $this->urlBuilderStub->expects($this->once())
            ->method('getUrl')
            ->with(
                'dhlpoland/shipment/updateStatus',
                ['entity_id' => 10]
            )
            ->willReturn('http://magento.local/admin/dhlpoland/shipment/updateStatus/entity_id/10');

        $buttonData = $this->button->getButtonData();

        $this->assertNotEmpty($buttonData);
        $this->assertEquals(__('Update Status'), $buttonData['label']);
        $this->assertStringContainsString("require(['mage/dataPost']", $buttonData['on_click']);
        $this->assertStringContainsString('dataPost().postData(', $buttonData['on_click']);
        $this->assertStringNotContainsString('location.href', $buttonData['on_click']);

        $matches = [];
        $this->assertSame(1, preg_match('/postData\((\{.*?\})\);/', $buttonData['on_click'], $matches));
        $postData = json_decode($matches[1], true);
        $this->assertSame(
            [
                'action' => 'http://magento.local/admin/dhlpoland/shipment/updateStatus/entity_id/10',
                'data' => ['redirect_to_edit' => 1],
            ],
            $postData
        );
    }
}
