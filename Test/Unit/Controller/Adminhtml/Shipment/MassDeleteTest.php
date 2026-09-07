<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Controller\Adminhtml\Shipment\MassDelete;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\Collection;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\ShipmentDeletionService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MassDeleteTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $contextMock;
    /**
     * @var MockObject
     */
    private $filterMock;
    /**
     * @var MockObject
     */
    private $collectionFactoryMock;
    /**
     * @var MockObject
     */
    private $deletionServiceMock;
    /**
     * @var MockObject
     */
    private $loggerMock;
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
     * @var MassDelete
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->filterMock = $this->createMock(Filter::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->deletionServiceMock = $this->createMock(ShipmentDeletionService::class);
        $this->loggerMock = $this->createStub(LoggerInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);

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

        $this->controller = new MassDelete(
            $this->contextMock,
            $this->filterMock,
            $this->collectionFactoryMock,
            $this->deletionServiceMock,
            $this->loggerMock
        );
    }

    public function testExecuteSuccess(): void
    {
        $collectionMock = $this->createMock(Collection::class);
        $shipment1 = $this->createMock(ShipmentDataInterface::class);
        $shipment1->expects($this->once())->method('getEntityId')->willReturn(1);
        $shipment2 = $this->createMock(ShipmentDataInterface::class);
        $shipment2->expects($this->once())->method('getEntityId')->willReturn(2);

        $collectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$shipment1, $shipment2]));

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->filterMock->expects($this->once())
            ->method('getCollection')
            ->with($collectionMock)
            ->willReturn($collectionMock);

        $this->deletionServiceMock->expects($this->exactly(2))
            ->method('deleteShipment')
            ->willReturn(true);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }
}
