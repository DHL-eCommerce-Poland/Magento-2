<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\Delete;
use Ideo\Dhl\Model\Shipment\ShipmentDeletionResult;
use Ideo\Dhl\Model\Shipment\ShipmentDeletionService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class DeleteTest extends TestCase
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
    private $messageManagerMock;
    /**
     * @var Stub
     */
    private $redirectFactoryStub;
    /**
     * @var Stub
     */
    private $redirectStub;
    /**
     * @var Stub
     */
    private $deletionServiceStub;
    /**
     * @var Stub
     */
    private $loggerStub;
    /**
     * @var Delete
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->redirectStub->method('setPath')->willReturnSelf();
        $this->deletionServiceStub = $this->createStub(ShipmentDeletionService::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getMessageManager')->willReturn($this->messageManagerMock);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($this->redirectFactoryStub);
        $this->redirectFactoryStub->method('create')->willReturn($this->redirectStub);

        $this->controller = new Delete(
            $this->contextStub,
            $this->deletionServiceStub,
            $this->loggerStub
        );
    }

    public function testExecuteSuccess(): void
    {
        $this->requestStub->method('getParam')
            ->willReturnCallback(static fn (string $param): ?string => $param === 'entity_id' ? '42' : null);

        $this->deletionServiceStub->method('deleteShipment')
            ->willReturn(new ShipmentDeletionResult(true));

        $calledPath = null;
        $this->redirectStub->method('setPath')->willReturnCallback(function (string $path) use (&$calledPath) {
            $calledPath = $path;
            return $this->redirectStub;
        });
        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
        $this->assertEquals('*/*/index', $calledPath);
    }

    public function testExecuteInvalidId(): void
    {
        $this->requestStub->method('getParam')
            ->willReturnCallback(static fn (string $param): ?string => $param === 'entity_id' ? '0' : null);

        $calledPath = null;
        $this->redirectStub->method('setPath')->willReturnCallback(function (string $path) use (&$calledPath) {
            $calledPath = $path;
            return $this->redirectStub;
        });
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
        $this->assertEquals('*/*/index', $calledPath);
    }

    public function testExecuteFailureResultAddsContextToErrorReason(): void
    {
        $this->requestStub->method('getParam')
            ->willReturnCallback(static fn (string $param): ?string => $param === 'entity_id' ? '42' : null);

        $errorMessage = '[90040166450] Nie znaleziono przesyłki o zadanym identyfikatorze';
        $this->deletionServiceStub->method('deleteShipment')
            ->willReturn(new ShipmentDeletionResult(false, $errorMessage));

        $calledPath = null;
        $calledParams = null;
        $this->redirectStub->method('setPath')->willReturnCallback(
            function (string $path, array $params = []) use (&$calledPath, &$calledParams) {
                $calledPath = $path;
                $calledParams = $params;
                return $this->redirectStub;
            }
        );
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Failed to delete shipment: ' . $errorMessage);

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
        $this->assertEquals('*/*/edit', $calledPath);
        $this->assertEquals(['entity_id' => 42], $calledParams);
    }

    public function testExecuteGenericExceptionAddsPrefixedMessage(): void
    {
        $this->requestStub->method('getParam')
            ->willReturnCallback(static fn (string $param): ?string => $param === 'entity_id' ? '42' : null);

        $this->deletionServiceStub->method('deleteShipment')
            ->willThrowException(new RuntimeException('Connection reset'));

        $calledPath = null;
        $calledParams = null;
        $this->redirectStub->method('setPath')->willReturnCallback(
            function (string $path, array $params = []) use (&$calledPath, &$calledParams) {
                $calledPath = $path;
                $calledParams = $params;
                return $this->redirectStub;
            }
        );
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(function ($phrase) {
                return (string) $phrase === 'Failed to delete shipment: Connection reset';
            }));

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
        $this->assertEquals('*/*/edit', $calledPath);
        $this->assertEquals(['entity_id' => 42], $calledParams);
    }
}
