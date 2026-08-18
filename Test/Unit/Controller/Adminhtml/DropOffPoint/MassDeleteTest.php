<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\DropOffPoint;

use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Controller\Adminhtml\DropOffPoint\MassDelete;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\Collection;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\TestCase;

class MassDeleteTest extends TestCase
{
    public function testExecuteDeletesSelectedItems(): void
    {
        $contextMock = $this->createStub(Context::class);
        $redirectFactoryMock = $this->createStub(RedirectFactory::class);
        $redirectMock = $this->createMock(Redirect::class);
        $messageManagerMock = $this->createMock(ManagerInterface::class);

        $redirectMock->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        $contextMock->method('getResultRedirectFactory')->willReturn($redirectFactoryMock);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);

        $filterMock = $this->createStub(Filter::class);
        $factoryMock = $this->createStub(CollectionFactory::class);
        $repoMock = $this->createMock(DropOffPointRepositoryInterface::class);

        $collectionMock = $this->createStub(Collection::class);
        $factoryMock->method('create')->willReturn($collectionMock);

        $itemMock = $this->createStub(DropOffPointInterface::class);
        $collectionMock->method('getIterator')->willReturn(new \ArrayIterator([$itemMock]));
        $filterMock->method('getCollection')->willReturn($collectionMock);

        $repoMock->expects($this->once())
            ->method('delete')
            ->with($itemMock);

        $messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $controller = new MassDelete(
            $contextMock,
            $filterMock,
            $factoryMock,
            $repoMock
        );

        $result = $controller->execute();
        $this->assertSame($redirectMock, $result);
    }
}
