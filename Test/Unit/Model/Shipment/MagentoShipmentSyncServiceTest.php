<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Carrier;
use Ideo\Dhl\Model\Shipment\MagentoShipmentSyncService;
use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItemModel;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Sender\EmailSender;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class MagentoShipmentSyncServiceTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $orderRepositoryMock;
    /**
     * @var MockObject
     */
    private $shipmentFactoryMock;
    /**
     * @var MockObject
     */
    private $trackFactoryMock;
    /**
     * @var MockObject
     */
    private $shipmentRepositoryMock;
    /**
     * @var MockObject
     */
    private $trackRepositoryMock;
    /**
     * @var MockObject
     */
    private $emailSenderMock;
    /**
     * @var MockObject
     */
    private $transactionFactoryMock;
    /**
     * @var MockObject
     */
    private $loggerMock;

    /**
     * @var MagentoShipmentSyncService
     */
    private $service;

    protected function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->shipmentFactoryMock = $this->createMock(ShipmentFactory::class);
        $this->trackFactoryMock = $this->createMock(TrackFactory::class);
        $this->shipmentRepositoryMock = $this->createMock(ShipmentRepositoryInterface::class);
        $this->trackRepositoryMock = $this->createMock(ShipmentTrackRepositoryInterface::class);
        $this->emailSenderMock = $this->createMock(EmailSender::class);
        $this->transactionFactoryMock = $this->createStub(TransactionFactory::class);
        $this->loggerMock = $this->createStub(LoggerInterface::class);

        $transactionMock = $this->createStub(Transaction::class);
        $this->transactionFactoryMock->method('create')->willReturn($transactionMock);

        $this->service = new MagentoShipmentSyncService(
            $this->orderRepositoryMock,
            $this->shipmentFactoryMock,
            $this->trackFactoryMock,
            $this->shipmentRepositoryMock,
            $this->trackRepositoryMock,
            $this->emailSenderMock,
            $this->transactionFactoryMock,
            $this->loggerMock
        );
    }

    public function testCreateMagentoShipmentAttachesToTargetShipmentWhenOrderCannotShip(): void
    {
        $orderId = 100;
        $targetShipmentId = 55;
        $trackingNumbers = ['TRACK999'];

        $orderMock = $this->createStub(Order::class);
        $orderMock->method('canShip')->willReturn(false);

        $targetShipmentMock = $this->createMock(Shipment::class);

        $this->orderRepositoryMock->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        $this->shipmentRepositoryMock->expects($this->once())
            ->method('get')
            ->with($targetShipmentId)
            ->willReturn($targetShipmentMock);

        $trackMock = $this->createStub(Track::class);
        $this->trackFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($trackMock);

        $targetShipmentMock->expects($this->once())
            ->method('addTrack')
            ->with($trackMock);

        $this->shipmentRepositoryMock->expects($this->once())
            ->method('save')
            ->with($targetShipmentMock)
            ->willReturn($targetShipmentMock);

        $result = $this->service->createMagentoShipment($orderId, $trackingNumbers, [], false, $targetShipmentId);
        $this->assertSame($targetShipmentMock, $result);
    }

    public function testCreateMagentoShipmentSuccessfullyCreatesShipmentAndSendsEmail(): void
    {
        $orderId = 100;
        $trackingNumbers = ['TRACK123', 'TRACK456'];

        $orderItemMock = $this->createStub(OrderItemModel::class);
        $orderItemMock->method('isDummy')->willReturn(false);
        $orderItemMock->method('getQtyToShip')->willReturn(2.0);
        $orderItemMock->method('getId')->willReturn(10);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('canShip')->willReturn(true);
        $orderMock->method('getAllItems')->willReturn([$orderItemMock]);

        $shipmentMock = $this->createMock(Shipment::class);

        $this->orderRepositoryMock->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        $this->shipmentFactoryMock->expects($this->once())
            ->method('create')
            ->with($orderMock, [10 => 2.0])
            ->willReturn($shipmentMock);

        $shipmentMock->expects($this->once())->method('register');

        $trackMock = $this->createStub(Track::class);
        $this->trackFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturn($trackMock);

        $shipmentMock->expects($this->exactly(2))
            ->method('addTrack')
            ->with($trackMock);

        $orderMock->expects($this->once())
            ->method('addCommentToStatusHistory');

        $this->emailSenderMock->expects($this->once())
            ->method('send')
            ->with($orderMock, $shipmentMock);

        $result = $this->service->createMagentoShipment($orderId, $trackingNumbers, [], true);
        $this->assertSame($shipmentMock, $result);
    }

    public function testUpdateMagentoShipmentTracksDeletesOldTracksAndAddsNew(): void
    {
        $magentoShipmentId = 50;
        $newTracks = ['NEWTRACK999'];

        $oldTrack = $this->createStub(Track::class);
        $oldTrack->method('getCarrierCode')->willReturn(Carrier::CODE);
        $oldTrack->method('getEntityId')->willReturn(99);

        $shipmentMock = $this->createMock(Shipment::class);
        $shipmentMock->method('getAllTracks')->willReturn([$oldTrack]);

        $this->shipmentRepositoryMock->expects($this->once())
            ->method('get')
            ->with($magentoShipmentId)
            ->willReturn($shipmentMock);

        $this->trackRepositoryMock->expects($this->once())
            ->method('delete')
            ->with($oldTrack);

        $newTrackMock = $this->createStub(Track::class);
        $this->trackFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($newTrackMock);

        $shipmentMock->expects($this->once())
            ->method('addTrack')
            ->with($newTrackMock);

        $this->shipmentRepositoryMock->expects($this->once())
            ->method('save')
            ->with($shipmentMock);

        $this->service->updateMagentoShipmentTracks($magentoShipmentId, $newTracks);
    }

    public function testCancelDhlTrackingRemovesTracksAndAddsOrderHistoryComment(): void
    {
        $magentoShipmentId = 50;
        $orderId = 100;

        $oldTrack = $this->createStub(Track::class);
        $oldTrack->method('getCarrierCode')->willReturn(Carrier::CODE);
        $oldTrack->method('getEntityId')->willReturn(88);

        $shipmentMock = $this->createMock(Shipment::class);
        $shipmentMock->method('getAllTracks')->willReturn([$oldTrack]);
        $shipmentMock->method('getOrderId')->willReturn($orderId);
        $shipmentMock->method('getIncrementId')->willReturn('100000050');

        $orderMock = $this->createMock(Order::class);
        $orderMock->expects($this->once())->method('addCommentToStatusHistory');

        $this->shipmentRepositoryMock->expects($this->once())
            ->method('get')
            ->with($magentoShipmentId)
            ->willReturn($shipmentMock);

        $this->trackRepositoryMock->expects($this->once())
            ->method('delete')
            ->with($oldTrack);

        $this->orderRepositoryMock->expects($this->once())
            ->method('get')
            ->with($orderId)
            ->willReturn($orderMock);

        $this->orderRepositoryMock->expects($this->once())
            ->method('save')
            ->with($orderMock);

        // Crucial for V5 Safe Mode: Shipment itself is NEVER deleted from ShipmentRepository!
        $this->shipmentRepositoryMock->expects($this->never())->method('delete');

        $this->service->cancelDhlTracking($magentoShipmentId);
    }
}
