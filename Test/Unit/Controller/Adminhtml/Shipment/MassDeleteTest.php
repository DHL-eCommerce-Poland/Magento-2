<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Controller\Adminhtml\Shipment\MassDelete;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\Collection;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use DhlPoland\Dhl\Model\Shipment\ShipmentDeletionService;
use DhlPoland\Dhl\Model\Shipment\ShipmentDeletionResult;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
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
        $this->loggerMock = $this->createMock(LoggerInterface::class);
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
            ->willReturn(new ShipmentDeletionResult(true));

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test that mass-delete failure details are included in the admin error message.
     *
     * @return void
     */
    public function testExecuteFailureShowsLocalizedReason(): void
    {
        $collectionMock = $this->createMock(Collection::class);
        $shipment = $this->createMock(ShipmentDataInterface::class);
        $shipment->expects($this->once())->method('getEntityId')->willReturn(6);
        $collectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$shipment]));

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);
        $this->filterMock->expects($this->once())
            ->method('getCollection')
            ->with($collectionMock)
            ->willReturn($collectionMock);

        $reason = '[90040166450] Courier collection is already in progress.';
        $this->deletionServiceMock->expects($this->once())
            ->method('deleteShipment')
            ->with(6)
            ->willReturn(new ShipmentDeletionResult(false, $reason));

        $this->loggerMock->expects($this->never())->method('error');

        $this->messageManagerMock->expects($this->never())->method('addSuccessMessage');
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(static function ($message) use ($reason): bool {
                return str_contains((string) $message, 'Shipment #6: ' . $reason)
                    && str_contains((string) $message, 'Details:');
            }));

        $result = $this->controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }
}
