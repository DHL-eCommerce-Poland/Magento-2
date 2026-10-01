<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use DateTime;
use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CourierBookingResult;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Shipment\CourierBookingService;
use Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CourierBookingServiceTest extends TestCase
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
     * @var MockObject
     */
    private $timezoneMock;
    /**
     * @var CourierBookingService
     */
    private $service;

    protected function setUp(): void
    {
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->shipmentDataRepositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);
        $this->timezoneMock = $this->createMock(TimezoneInterface::class);
        $this->timezoneMock->method('scopeDate')
            ->willReturnCallback(function ($scope = null, $date = null) {
                return new DateTime($date ? (string)$date : 'today');
            });

        $this->timezoneMock->method('date')
            ->willReturnCallback(fn ($d) => new DateTime($d));

        $this->credentialsProviderStub->method('getCredentials')
            ->willReturn(new Credentials('user', 'pass', true));

        $dateSanitizer = new ShipmentDateSanitizer($this->timezoneMock);

        $this->service = new CourierBookingService(
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub,
            $this->timezoneMock,
            $dateSanitizer,
            $this->createStub(\Psr\Log\LoggerInterface::class)
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
        $this->expectExceptionMessage(
            'None of the selected shipments have valid DHL Shipment IDs for courier booking.'
        );

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

    public function testBookCourierAllowsFutureDateAndCallsApiClient(): void
    {
        $entityMock = $this->createMock(ShipmentDataInterface::class);
        $entityMock->method('getShipmentId')->willReturn('1234567890');
        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityMock);

        $bookingResult = new CourierBookingResult(true, 'ORDER_FUTURE', null, null, 'OK');

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('bookCourier')
            ->with(
                $this->anything(),
                ['1234567890'],
                '2028-09-26',
                '10:00',
                '14:00',
                $this->anything()
            )
            ->willReturn($bookingResult);

        $service = new CourierBookingService(
            $clientMock,
            $this->credentialsProviderStub,
            $this->shipmentDataRepositoryStub,
            $this->timezoneMock,
            new ShipmentDateSanitizer($this->timezoneMock),
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );

        $result = $service->bookCourier([10], '2028-09-26', '10:00', '14:00');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ORDER_FUTURE', $result->getCourierOrderId());
    }
}
