<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CourierBookingResult;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Shipment\CourierBookingService;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class CourierBookingServiceTest extends TestCase
{
    private ClientInterface|Stub $clientStub;
    private CredentialsProvider|Stub $credentialsProviderStub;
    private ShipmentDataRepositoryInterface|Stub $shipmentDataRepositoryStub;
    private CourierBookingService $service;

    protected function setUp(): void
    {
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->shipmentDataRepositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);

        $this->credentialsProviderStub->method('getCredentials')
            ->willReturn(new Credentials('user', 'pass', true));

        $this->service = new CourierBookingService(
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub
        );
    }

    public function testBookCourierThrowsExceptionWhenEmptyIds(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No shipments selected for courier booking.');

        $this->service->bookCourier([], '2026-08-28', '10:00', '14:00');
    }

    public function testBookCourierThrowsExceptionWhenNoValidDhlIdsFound(): void
    {
        $entityStub = $this->createStub(ShipmentDataInterface::class);
        $entityStub->method('getShipmentId')->willReturn(null);

        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityStub);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('None of the selected shipments have valid DHL Shipment IDs for courier booking.');

        $this->service->bookCourier([10], '2026-08-28', '10:00', '14:00');
    }

    public function testBookCourierSuccess(): void
    {
        $entityMock = $this->createMock(ShipmentDataInterface::class);
        $entityMock->method('getShipmentId')->willReturn('1234567890');
        $entityMock->expects($this->once())
            ->method('setStatus')
            ->with((string) __('Kurier zamówiony'));

        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityMock);

        $bookingResult = new CourierBookingResult(true, 'ORDER_999', null, null, (string) __('Kurier zamówiony'));

        $this->clientStub->method('bookCourier')
            ->willReturn($bookingResult);

        $result = $this->service->bookCourier(
            [10],
            '2026-08-28',
            '10:00',
            '14:00',
            'Handle with care'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ORDER_999', $result->getCourierOrderId());
    }
}
