<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\CustomsTemplate;

use Exception;
use DhlPoland\Dhl\Controller\Adminhtml\CustomsTemplate\Delete;
use DhlPoland\Dhl\Model\CustomsTemplateRepository;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    /**
     * @var Context
     */
    private Context $contextMock;

    /**
     * @var RequestInterface&MockObject
     */
    private $requestMock;

    /**
     * @var ManagerInterface&MockObject
     */
    private $messageManagerMock;

    /**
     * @var RedirectFactory
     */
    private RedirectFactory $redirectFactoryMock;

    /**
     * @var Redirect&MockObject
     */
    private $redirectMock;

    /**
     * @var CustomsTemplateRepository&MockObject
     */
    private $repositoryMock;

    /**
     * @var Delete
     */
    private Delete $controller;

    protected function setUp(): void
    {
        $this->contextMock = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->redirectFactoryMock = $this->createStub(RedirectFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        $this->repositoryMock = $this->createMock(CustomsTemplateRepository::class);

        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->method('getMessageManager')->willReturn($this->messageManagerMock);
        $this->contextMock->method('getResultRedirectFactory')->willReturn($this->redirectFactoryMock);
        $this->redirectFactoryMock->method('create')->willReturn($this->redirectMock);

        $this->controller = new Delete(
            $this->contextMock,
            $this->repositoryMock
        );
    }

    public function testExecuteSuccessRedirectsToGrid(): void
    {
        $this->requestMock->method('getParam')->with('entity_id')->willReturn('7');
        $this->repositoryMock->expects($this->once())->method('deleteById')->with(7);
        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(fn($phrase) => (string)$phrase === 'You deleted the Customs Clearance.'));
        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteExceptionRedirectsToEdit(): void
    {
        $this->requestMock->method('getParam')->with('entity_id')->willReturn('7');
        $this->repositoryMock->expects($this->once())
            ->method('deleteById')
            ->with(7)
            ->willThrowException(new Exception('Database error'));
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with('Database error');
        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('*/*/edit', ['entity_id' => 7])
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteWithoutEntityIdRedirectsToGridWithErrorMessage(): void
    {
        $this->requestMock->method('getParam')->with('entity_id')->willReturn(null);
        $this->repositoryMock->expects($this->never())->method('deleteById');
        $expectedMessage = 'We can not find a Customs Clearance to delete.';
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(fn($phrase) => (string)$phrase === $expectedMessage));
        $this->redirectMock->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }
}
