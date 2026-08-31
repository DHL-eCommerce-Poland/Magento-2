<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\UpdateStatus;
use Ideo\Dhl\Model\Api\TrackingResult;
use Ideo\Dhl\Model\Shipment\ShipmentTrackingService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UpdateStatusTest extends TestCase
{
    private Context|Stub $contextStub;
    private RequestInterface|Stub $requestStub;
    private RedirectFactory|Stub $redirectFactoryStub;
    private Redirect|Stub $redirectStub;
    private ManagerInterface|Stub $messageManagerStub;
    private ShipmentTrackingService|Stub $trackingServiceStub;
    private UpdateStatus $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->messageManagerStub = $this->createStub(ManagerInterface::class);
        $this->trackingServiceStub = $this->createStub(ShipmentTrackingService::class);

        $this->contextStub->method('getRequest')
            ->willReturn($this->requestStub);

        $this->contextStub->method('getResultRedirectFactory')
            ->willReturn($this->redirectFactoryStub);

        $this->contextStub->method('getMessageManager')
            ->willReturn($this->messageManagerStub);

        $this->redirectFactoryStub->method('create')
            ->willReturn($this->redirectStub);

        $this->controller = new UpdateStatus(
            $this->contextStub,
            $this->trackingServiceStub
        );
    }

    public function testExecuteWithInvalidEntityId(): void
    {
        $this->requestStub->method('getParam')
            ->willReturnMap([
                ['entity_id', null, 0],
                ['order_id', null, null],
            ]);

        $this->redirectStub->method('setPath')
            ->willReturnSelf();

        $this->assertSame($this->redirectStub, $this->controller->execute());
    }

    public function testExecuteSuccess(): void
    {
        $this->requestStub->method('getParam')
            ->willReturnMap([
                ['entity_id', null, 5],
                ['order_id', null, null],
            ]);

        $trackingResult = new TrackingResult(
            success: true,
            shipmentId: '1234567890',
            receivedBy: null,
            events: [
                ['status' => 'DOR', 'description' => 'Doręczono', 'terminal' => 'Warszawa', 'timestamp' => '2026-08-28 14:00:00'],
            ]
        );

        $this->trackingServiceStub->method('updateStatus')
            ->willReturn($trackingResult);

        $this->redirectStub->method('setPath')
            ->willReturnSelf();

        $this->assertSame($this->redirectStub, $this->controller->execute());
    }
}
