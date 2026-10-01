<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\MassUpdateStatus;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\Collection;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\ShipmentTrackingService;
use Ideo\Dhl\Model\ShipmentData;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class MassUpdateStatusTest extends TestCase
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
    private $trackingServiceMock;
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
     * @var MassUpdateStatus
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->filterMock = $this->createMock(Filter::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->trackingServiceMock = $this->createMock(ShipmentTrackingService::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);

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

        $this->controller = new MassUpdateStatus(
            $this->contextStub,
            $this->filterMock,
            $this->collectionFactoryMock,
            $this->trackingServiceMock,
            $this->createStub(\Psr\Log\LoggerInterface::class)
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

        $this->trackingServiceMock->expects($this->once())
            ->method('massUpdateStatus')
            ->with([10, 20])
            ->willReturn([
                'success' => [10 => 'Doręczono', 20 => 'W drodze'],
                'errors' => [],
            ]);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }
}
