<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\CancelCourierBookingResult;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Shipment\CourierBookingCancellationService;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CourierBookingCancellationServiceTest extends TestCase
{
    private ClientInterface|Stub $clientStub;
    private CredentialsProvider|Stub $credentialsProviderStub;
    private ShipmentDataRepositoryInterface|Stub $shipmentDataRepositoryStub;
    private CourierBookingCancellationService $service;

    protected function setUp(): void
    {
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->shipmentDataRepositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);

        $this->credentialsProviderStub->method('getCredentials')
            ->willReturn(new Credentials('user', 'pass', true));

        $this->service = new CourierBookingCancellationService(
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub
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

        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityStub);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('None of the selected shipments have an active courier booking/dispatch number to cancel.');

        $this->service->cancelCourierBooking([10]);
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
}
