<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\Delete;
use Ideo\Dhl\Model\Shipment\ShipmentDeletionService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DeleteTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $contextMock;
    /**
     * @var MockObject
     */
    private $requestMock;
    /**
     * @var MockObject
     */
    private $messageManagerMock;
    /**
     * @var MockObject
     */
    private $redirectFactoryMock;
    /**
     * @var MockObject
     */
    private $redirectMock;
    /**
     * @var MockObject
     */
    private $deletionServiceMock;
    /**
     * @var MockObject
     */
    private $loggerMock;
    /**
     * @var Delete
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        $this->deletionServiceMock = $this->createMock(ShipmentDeletionService::class);
        $this->loggerMock = $this->createStub(LoggerInterface::class);

        $this->contextMock->expects($this->once())->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->expects($this->once())->method('getMessageManager')->willReturn($this->messageManagerMock);
        $this->contextMock->expects($this->once())
            ->method('getResultRedirectFactory')
            ->willReturn($this->redirectFactoryMock);

        $this->redirectFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->redirectMock);

        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $this->controller = new Delete(
            $this->contextMock,
            $this->deletionServiceMock,
            $this->loggerMock
        );
    }

    public function testExecuteSuccess(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn('42');

        $this->deletionServiceMock->expects($this->once())
            ->method('deleteShipment')
            ->with(42)
            ->willReturn(true);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteInvalidId(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn('0');

        $this->deletionServiceMock->expects($this->never())
            ->method('deleteShipment');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteLocalizedExceptionAddsDirectMessageWithoutDuplicatePrefix(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn('42');

        $errorMessage = 'Nie udało się usunąć przesyłki w DHL: Nie znaleziono przesyłki o zadanym identyfikatorze';
        $this->deletionServiceMock->expects($this->once())
            ->method('deleteShipment')
            ->with(42)
            ->willThrowException(new \Magento\Framework\Exception\LocalizedException(__($errorMessage)));

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($errorMessage);

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteGenericExceptionAddsPrefixedMessage(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn('42');

        $this->deletionServiceMock->expects($this->once())
            ->method('deleteShipment')
            ->with(42)
            ->willThrowException(new \RuntimeException('Connection reset'));

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(function ($phrase) {
                return (string)$phrase === 'Failed to delete shipment: Connection reset';
            }));

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }
}

