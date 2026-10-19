<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Controller\Adminhtml\Shipment\CancelCourierBooking;
use DhlPoland\Dhl\Model\Api\CancelCourierBookingResult;
use DhlPoland\Dhl\Model\Shipment\CourierBookingCancellationService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CancelCourierBookingTest extends TestCase
{
    /**
     * @var Stub
     */
    private $contextStub;
    /**
     * @var Stub
     */
    private $requestStub;
    /**
     * @var MockObject
     */
    private $cancellationServiceMock;
    /**
     * @var Stub
     */
    private $redirectFactoryStub;
    /**
     * @var Stub
     */
    private $redirectStub;
    /**
     * @var MockObject
     */
    private $messageManagerMock;
    /**
     * @var Stub
     */
    private $redirectHelperStub;
    /**
     * @var Stub
     */
    private $loggerStub;
    /**
     * @var CancelCourierBooking
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->cancellationServiceMock = $this->createMock(CourierBookingCancellationService::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->redirectHelperStub = $this->createStub(RedirectInterface::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($this->redirectFactoryStub);
        $this->contextStub->method('getMessageManager')->willReturn($this->messageManagerMock);
        $this->contextStub->method('getRedirect')->willReturn($this->redirectHelperStub);

        $this->redirectFactoryStub->method('create')->willReturn($this->redirectStub);
        $this->redirectStub->method('setPath')->willReturnSelf();
        $this->redirectStub->method('setUrl')->willReturnSelf();

        $this->controller = new CancelCourierBooking(
            $this->contextStub,
            $this->cancellationServiceMock,
            $this->loggerStub
        );
    }

    public function testExecuteNoShipmentsSelected(): void
    {
        $this->requestStub->method('getParam')->willReturnMap([
            ['selected', [], []],
            ['entity_id', null, null],
        ]);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');
        $this->cancellationServiceMock->expects($this->never())
            ->method('cancelCourierBooking');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }

    public function testExecuteSuccess(): void
    {
        $this->requestStub->method('getParam')->willReturnMap([
            ['selected', [], []],
            ['entity_id', null, '10'],
        ]);

        $cancelResult = new CancelCourierBookingResult(true, 'ORDER_10', null, null, ['ORDER_10'], []);
        $this->cancellationServiceMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with(['10'])
            ->willReturn($cancelResult);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');
        $this->redirectHelperStub->method('getRefererUrl')
            ->willReturn('http://example.com/referer');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }

    public function testExecuteCancellationFailure(): void
    {
        $this->requestStub->method('getParam')->willReturnMap([
            ['selected', [], ['10']],
            ['entity_id', null, null],
        ]);

        $cancelResult = new CancelCourierBookingResult(
            false,
            'ORDER_10',
            'ERR',
            'Courier not found',
            [],
            ['ORDER_10' => 'Courier not found']
        );
        $this->cancellationServiceMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with(['10'])
            ->willReturn($cancelResult);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }

    public function testExecutePartialCancellation(): void
    {
        $this->requestStub->method('getParam')->willReturnMap([
            ['selected', [], ['10', '20']],
            ['entity_id', null, null],
        ]);

        $cancelResult = new CancelCourierBookingResult(
            false,
            'ORDER_10',
            'PARTIAL_ERROR',
            'ORDER_20: Failed',
            ['ORDER_10'],
            ['ORDER_20' => 'ORDER_20: Failed']
        );
        $this->cancellationServiceMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with(['10', '20'])
            ->willReturn($cancelResult);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }

    public function testExecuteExceptionLogsAndAddsErrorMessage(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $controller = new CancelCourierBooking(
            $this->contextStub,
            $this->cancellationServiceMock,
            $loggerMock
        );

        $this->requestStub->method('getParam')->willReturnMap([
            ['selected', [], ['10']],
            ['entity_id', null, null],
        ]);

        $this->cancellationServiceMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->willThrowException(new RuntimeException('Unexpected DB error'));

        $loggerMock->expects($this->once())
            ->method('error');
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }
}
