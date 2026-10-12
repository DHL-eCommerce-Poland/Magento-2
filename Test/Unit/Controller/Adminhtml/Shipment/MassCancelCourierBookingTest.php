<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Controller\Adminhtml\Shipment\MassCancelCourierBooking;
use DhlPoland\Dhl\Model\Api\CancelCourierBookingResult;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\Collection;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use DhlPoland\Dhl\Model\Shipment\CourierBookingCancellationService;
use DhlPoland\Dhl\Model\ShipmentData;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MassCancelCourierBookingTest extends TestCase
{
    /**
     * @var Stub
     */
    private $contextStub;
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
    private $cancellationServiceMock;
    /**
     * @var Stub
     */
    private $redirectFactoryStub;
    /**
     * @var MockObject
     */
    private $redirectMock;
    /**
     * @var MockObject
     */
    private $messageManagerMock;
    /**
     * @var Stub
     */
    private $loggerStub;
    /**
     * @var MassCancelCourierBooking
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->filterMock = $this->createMock(Filter::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->cancellationServiceMock = $this->createMock(CourierBookingCancellationService::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->contextStub->method('getResultRedirectFactory')
            ->willReturn($this->redirectFactoryStub);

        $this->contextStub->method('getMessageManager')
            ->willReturn($this->messageManagerMock);

        $this->redirectFactoryStub->method('create')
            ->willReturn($this->redirectMock);

        $this->redirectMock->expects($this->any())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $this->controller = new MassCancelCourierBooking(
            $this->contextStub,
            $this->filterMock,
            $this->collectionFactoryMock,
            $this->cancellationServiceMock,
            $this->loggerStub
        );
    }

    public function testExecuteSuccess(): void
    {
        $itemMock1 = $this->createStub(ShipmentData::class);
        $itemMock1->method('getEntityId')->willReturn(10);
        $itemMock2 = $this->createStub(ShipmentData::class);
        $itemMock2->method('getEntityId')->willReturn(20);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$itemMock1, $itemMock2]));

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->filterMock->expects($this->once())
            ->method('getCollection')
            ->with($collectionMock)
            ->willReturn($collectionMock);

        $cancelResult = new CancelCourierBookingResult(true, 'ORDER_10');
        $this->cancellationServiceMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with([10, 20])
            ->willReturn($cancelResult);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteNoShipmentsSelected(): void
    {
        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([]));

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->filterMock->expects($this->once())
            ->method('getCollection')
            ->with($collectionMock)
            ->willReturn($collectionMock);

        $this->cancellationServiceMock->expects($this->never())
            ->method('cancelCourierBooking');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteCancellationFailure(): void
    {
        $itemMock = $this->createStub(ShipmentData::class);
        $itemMock->method('getEntityId')->willReturn(10);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$itemMock]));

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->filterMock->expects($this->once())
            ->method('getCollection')
            ->with($collectionMock)
            ->willReturn($collectionMock);

        $cancelResult = new CancelCourierBookingResult(false, null, 'ERR', 'API Error');
        $this->cancellationServiceMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with([10])
            ->willReturn($cancelResult);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    /**
     * Test that expected localized cancellation failures are shown without exception logging.
     *
     * @return void
     */
    public function testExecuteLocalizedCancellationFailureIsNotLoggedAsException(): void
    {
        $this->collectionFactoryMock->expects($this->never())->method('create');
        $this->filterMock->expects($this->never())->method('getCollection');

        $itemStub = $this->createStub(ShipmentData::class);
        $itemStub->method('getEntityId')->willReturn(10);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$itemStub]));
        $collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);
        $filterMock = $this->createMock(Filter::class);
        $filterMock->expects($this->once())
            ->method('getCollection')
            ->with($collectionMock)
            ->willReturn($collectionMock);

        $message = 'Żadna z wybranych przesyłek nie posiada aktywnego numeru zlecenia kuriera do anulowania.';
        $this->cancellationServiceMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with([10])
            ->willThrowException(new LocalizedException(__($message)));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->never())->method('error');
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($message);

        $controller = new MassCancelCourierBooking(
            $this->contextStub,
            $filterMock,
            $collectionFactoryMock,
            $this->cancellationServiceMock,
            $loggerMock
        );

        $result = $controller->execute();

        $this->assertSame($this->redirectMock, $result);
    }
}
