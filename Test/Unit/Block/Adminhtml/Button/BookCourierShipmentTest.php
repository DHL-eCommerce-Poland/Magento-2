<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Block\Adminhtml\Button;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Block\Adminhtml\Button\BookCourierShipment;
use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class BookCourierShipmentTest extends TestCase
{
    /**
     * @var Context|Stub
     */
    private $contextStub;

    /**
     * @var AdminhtmlShipmentData|Stub
     */
    private $shipmentDataStub;

    /**
     * @var Escaper|Stub
     */
    private $escaperStub;

    /**
     * @var BookCourierShipment
     */
    private $button;

    /**
     * Prepare the button with lightweight Magento dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->shipmentDataStub = $this->createStub(AdminhtmlShipmentData::class);
        $this->escaperStub = $this->createStub(Escaper::class);
        $this->escaperStub->method('escapeJs')->willReturnCallback(
            static fn(string $value): string => addslashes($value)
        );

        $this->button = new BookCourierShipment(
            $this->contextStub,
            $this->escaperStub,
            $this->shipmentDataStub
        );
    }

    /**
     * Test that the modal receives no displayed shipment numbers when DHL shipment ID is absent.
     *
     * @return void
     */
    public function testGetButtonDataDoesNotUseEntityIdWhenDhlShipmentIdIsMissing(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getDispatchNumber')->willReturn(null);
        $shipment->method('getShipmentId')->willReturn(null);
        $this->shipmentDataStub->method('getEntityId')->willReturn(42);
        $this->shipmentDataStub->method('getShipment')->willReturn($shipment);

        $buttonData = $this->button->getButtonData();

        $this->assertStringContainsString("openBookCourierModal')([42], [])", $buttonData['on_click']);
        $this->assertStringContainsString('openModal([42], [])', $buttonData['on_click']);
    }

    /**
     * Test that the modal receives the actual DHL shipment ID when it is available.
     *
     * @return void
     */
    public function testGetButtonDataPassesDhlShipmentIdToModal(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getDispatchNumber')->willReturn(null);
        $shipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_42');
        $this->shipmentDataStub->method('getEntityId')->willReturn(42);
        $this->shipmentDataStub->method('getShipment')->willReturn($shipment);

        $buttonData = $this->button->getButtonData();

        $this->assertStringContainsString(
            "openBookCourierModal')([42], ['DHL_SHIPMENT_42'])",
            $buttonData['on_click']
        );
        $this->assertStringContainsString("openModal([42], ['DHL_SHIPMENT_42'])", $buttonData['on_click']);
    }
}
