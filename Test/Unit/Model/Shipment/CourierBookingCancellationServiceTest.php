<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use ArrayIterator;
use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\CancelCourierBookingResult;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\Collection as ShipmentDataCollection;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory as ShipmentDataCollectionFactory;
use Ideo\Dhl\Model\Shipment\CourierBookingCancellationService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CourierBookingCancellationServiceTest extends TestCase
{
    /**
     * @var Stub
     */
    private $clientStub;
    /**
     * @var Stub
     */
    private $credentialsProviderStub;
    /**
     * @var Stub
     */
    private $shipmentDataRepositoryStub;
    /**
     * @var Stub
     */
    private $collectionFactoryStub;
    /**
     * @var CourierBookingCancellationService
     */
    private $service;

    protected function setUp(): void
    {
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->shipmentDataRepositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);
        $this->collectionFactoryStub = $this->createStub(ShipmentDataCollectionFactory::class);

        $this->credentialsProviderStub->method('getCredentials')
            ->willReturn(new Credentials('user', 'pass', true));

        $this->service = new CourierBookingCancellationService(
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub,
            $this->collectionFactoryStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );
    }

    public function testCancelCourierBookingThrowsExceptionWhenEmptyIds(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No shipments or courier orders selected for cancellation.');

        $this->service->cancelCourierBooking([]);
    }

    public function testCancelCourierBookingThrowsExceptionWhenNoDispatchNumber(): void
    {
        $entityStub = $this->createStub(ShipmentDataInterface::class);
        $entityStub->method('getDispatchNumber')->willReturn(null);
        $entityStub->method('getStatus')->willReturn('Courier status (STATUS_TOKEN)');
        $entityStub->method('getShipmentId')->willReturn('DHL_SHIPMENT_10');

        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityStub);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->never())->method('cancelCourierBooking');
        $service = new CourierBookingCancellationService(
            $clientMock,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub,
            $this->collectionFactoryStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'None of the selected shipments have an active courier dispatch number to cancel.'
        );

        $service->cancelCourierBooking([10]);
    }

    /**
     * Test that only selected records with a courier dispatch number are sent for cancellation.
     *
     * @return void
     */
    public function testCancelCourierBookingSkipsRecordsWithoutDispatchNumber(): void
    {
        $entityWithDispatchNumber = $this->createStub(ShipmentDataInterface::class);
        $entityWithDispatchNumber->method('getDispatchNumber')->willReturn('COURIER_ORDER_10');

        $entityWithoutDispatchNumber = $this->createStub(ShipmentDataInterface::class);
        $entityWithoutDispatchNumber->method('getDispatchNumber')->willReturn(null);
        $entityWithoutDispatchNumber->method('getStatus')->willReturn('Courier status (STATUS_TOKEN)');
        $entityWithoutDispatchNumber->method('getShipmentId')->willReturn('DHL_SHIPMENT_20');

        $this->shipmentDataRepositoryStub->method('getById')->willReturnMap([
            [10, $entityWithDispatchNumber],
            [20, $entityWithoutDispatchNumber],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with($this->anything(), ['COURIER_ORDER_10'])
            ->willReturn(new CancelCourierBookingResult(false, null, 'ERR', 'Expected test failure'));

        $service = new CourierBookingCancellationService(
            $clientMock,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub,
            $this->collectionFactoryStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $result = $service->cancelCourierBooking([10, 20]);

        $this->assertFalse($result->isSuccess());
    }

    /**
     * Test that a directly supplied courier order number remains supported.
     *
     * @return void
     */
    public function testCancelCourierBookingAcceptsExplicitCourierOrderNumber(): void
    {
        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with($this->anything(), ['COURIER_ORDER_10'])
            ->willReturn(new CancelCourierBookingResult(false, null, 'ERR', 'Expected test failure'));

        $service = new CourierBookingCancellationService(
            $clientMock,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub,
            $this->collectionFactoryStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $result = $service->cancelCourierBooking([' COURIER_ORDER_10 ']);

        $this->assertFalse($result->isSuccess());
    }

    public function testCancelCourierBookingSuccess(): void
    {
        $entityMock = $this->createMock(ShipmentDataInterface::class);
        $entityMock->method('getDispatchNumber')->willReturn('90018060826WWW');
        $entityMock->expects($this->once())
            ->method('setDispatchNumber')
            ->with(null);
        $entityMock->expects($this->once())
            ->method('setStatus')
            ->with((string) __('Nowa'));

        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityMock);

        $cancelResult = new CancelCourierBookingResult(true, '90018060826WWW');

        $this->clientStub->method('cancelCourierBooking')
            ->willReturn($cancelResult);

        $result = $this->service->cancelCourierBooking([10]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('90018060826WWW', $result->getDispatchNumber());
    }

    public function testCancelCourierBookingResetsAllEntitiesSharingDispatchNumber(): void
    {
        $entity1Mock = $this->createMock(ShipmentDataInterface::class);
        $entity1Mock->method('getEntityId')->willReturn(10);
        $entity1Mock->method('getDispatchNumber')->willReturn('90018060826WWW');
        $entity1Mock->expects($this->once())->method('setDispatchNumber')->with(null);
        $entity1Mock->expects($this->once())->method('setStatus')->with((string) __('Nowa'));

        $entity2Mock = $this->createMock(ShipmentDataInterface::class);
        $entity2Mock->method('getEntityId')->willReturn(11);
        $entity2Mock->method('getDispatchNumber')->willReturn('90018060826WWW');
        $entity2Mock->expects($this->once())->method('setDispatchNumber')->with(null);
        $entity2Mock->expects($this->once())->method('setStatus')->with((string) __('Nowa'));

        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entity1Mock);

        $collectionMock = $this->createMock(ShipmentDataCollection::class);
        $collectionMock->expects($this->once())
            ->method('addFieldToFilter')
            ->with(ShipmentDataInterface::DISPATCH_NUMBER, ['in' => ['90018060826WWW']]);
        $collectionMock->method('getIterator')
            ->willReturn(new ArrayIterator([$entity1Mock, $entity2Mock]));

        $collectionFactoryStub = $this->createStub(ShipmentDataCollectionFactory::class);
        $collectionFactoryStub->method('create')->willReturn($collectionMock);

        $cancelResult = new CancelCourierBookingResult(true, '90018060826WWW');
        $this->clientStub->method('cancelCourierBooking')->willReturn($cancelResult);

        $serviceWithCollection = new CourierBookingCancellationService(
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub,
            $collectionFactoryStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $result = $serviceWithCollection->cancelCourierBooking([10]);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test that cancelCourierBooking calls credentialsProvider getCredentials.
     *
     * @return void
     */
    public function testCancelCourierBookingCallsCredentialsProvider(): void
    {
        $credentialsProviderMock = $this->createMock(CredentialsProvider::class);
        $credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->willReturn(new Credentials('prod_user', 'prod_pass', false));

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('cancelCourierBooking')
            ->with($this->callback(fn (Credentials $c) => !$c->isSandbox()), ['COURIER_PROD_1'])
            ->willReturn(new CancelCourierBookingResult(true, 'COURIER_PROD_1'));

        $service = new CourierBookingCancellationService(
            $clientMock,
            $credentialsProviderMock,
            $this->shipmentDataRepositoryStub,
            $this->collectionFactoryStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $result = $service->cancelCourierBooking(['COURIER_PROD_1']);
        $this->assertTrue($result->isSuccess());
    }
}
