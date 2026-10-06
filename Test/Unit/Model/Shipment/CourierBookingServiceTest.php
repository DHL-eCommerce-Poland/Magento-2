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
use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
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
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new ShipmentDataPayloadReader(new Json())
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
        $entityStub->method('getShipmentData')->willReturn($this->shipmentPayload('AH'));

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
        $entityMock->method('getShipmentData')->willReturn($this->shipmentPayload('AH'));
        $entityMock->expects($this->once())
            ->method('setStatus')
            ->with((string) __('Kurier zamówiony'));
        $entityMock->expects($this->once())
            ->method('setDispatchNumber')
            ->with('ORDER_999');

        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityMock);

        $bookingResult = new CourierBookingResult(true, ['ORDER_999'], null, null, (string) __('Kurier zamówiony'));

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
        $this->assertEquals(['ORDER_999'], $result->getCourierOrderIds());
    }

    /**
     * Test that each DHL courier order number is assigned to its matching shipment.
     *
     * @return void
     */
    public function testBookCourierAssignsEachReturnedOrderNumberToItsShipment(): void
    {
        $firstShipment = $this->createMock(ShipmentDataInterface::class);
        $firstShipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_1');
        $firstShipment->method('getShipmentData')->willReturn($this->shipmentPayload('AH'));
        $firstShipment->expects($this->once())->method('setStatus');
        $firstShipment->expects($this->once())->method('setDispatchNumber')->with('90002561026WWW');

        $secondShipment = $this->createMock(ShipmentDataInterface::class);
        $secondShipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_2');
        $secondShipment->method('getShipmentData')->willReturn($this->shipmentPayload('EK'));
        $secondShipment->expects($this->once())->method('setStatus');
        $secondShipment->expects($this->once())->method('setDispatchNumber')->with('90002571026WWW');

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->exactly(4))
            ->method('getById')
            ->willReturnMap([
                [10, $firstShipment],
                [20, $secondShipment],
            ]);
        $repository->expects($this->exactly(2))->method('save');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('bookCourier')
            ->with(
                $this->anything(),
                ['DHL_SHIPMENT_1', 'DHL_SHIPMENT_2'],
                '2026-10-06',
                '10:00',
                '14:00',
                null
            )
            ->willReturn(new CourierBookingResult(true, ['90002561026WWW', '90002571026WWW']));

        $service = new CourierBookingService(
            $client,
            $this->credentialsProviderStub,
            $repository,
            $this->timezoneMock,
            new ShipmentDateSanitizer($this->timezoneMock),
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new ShipmentDataPayloadReader(new Json())
        );

        $result = $service->bookCourier([10, 20], '2026-10-06', '10:00', '14:00');

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test that an incomplete DHL response does not assign an order number to any shipment.
     *
     * @return void
     */
    public function testBookCourierDoesNotAssignNumbersWhenResponseCountDoesNotMatchShipments(): void
    {
        $firstShipment = $this->createStub(ShipmentDataInterface::class);
        $firstShipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_1');
        $firstShipment->method('getShipmentData')->willReturn($this->shipmentPayload('AH'));
        $secondShipment = $this->createStub(ShipmentDataInterface::class);
        $secondShipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_2');
        $secondShipment->method('getShipmentData')->willReturn($this->shipmentPayload('EK'));

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->exactly(2))
            ->method('getById')
            ->willReturnMap([
                [10, $firstShipment],
                [20, $secondShipment],
            ]);
        $repository->expects($this->never())->method('save');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('bookCourier')
            ->willReturn(new CourierBookingResult(true, ['90002561026WWW']));

        $service = new CourierBookingService(
            $client,
            $this->credentialsProviderStub,
            $repository,
            $this->timezoneMock,
            new ShipmentDateSanitizer($this->timezoneMock),
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new ShipmentDataPayloadReader(new Json())
        );

        $result = $service->bookCourier([10, 20], '2026-08-28', '10:00', '14:00');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('INVALID_RESPONSE', $result->getErrorCode());
    }

    public function testBookCourierAllowsFutureDateAndCallsApiClient(): void
    {
        $entityMock = $this->createMock(ShipmentDataInterface::class);
        $entityMock->method('getShipmentId')->willReturn('1234567890');
        $entityMock->method('getShipmentData')->willReturn($this->shipmentPayload('AH'));
        $this->shipmentDataRepositoryStub->method('getById')
            ->willReturn($entityMock);

        $bookingResult = new CourierBookingResult(true, ['ORDER_FUTURE'], null, null, 'OK');

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
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new ShipmentDataPayloadReader(new Json())
        );

        $result = $service->bookCourier([10], '2028-09-26', '10:00', '14:00');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['ORDER_FUTURE'], $result->getCourierOrderIds());
    }

    public function testBookCourierAssignsOneOrderNumberToAllShipmentsWithSameServiceType(): void
    {
        $firstShipment = $this->createMock(ShipmentDataInterface::class);
        $firstShipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_1');
        $firstShipment->method('getShipmentData')->willReturn($this->shipmentPayload('AH'));
        $firstShipment->expects($this->once())->method('setDispatchNumber')->with('ORDER_AH');
        $firstShipment->expects($this->once())->method('setStatus');

        $secondShipment = $this->createMock(ShipmentDataInterface::class);
        $secondShipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_2');
        $secondShipment->method('getShipmentData')->willReturn($this->shipmentPayload('AH'));
        $secondShipment->expects($this->once())->method('setDispatchNumber')->with('ORDER_AH');
        $secondShipment->expects($this->once())->method('setStatus');

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->exactly(4))
            ->method('getById')
            ->willReturnMap([
                [10, $firstShipment],
                [20, $secondShipment],
            ]);
        $repository->expects($this->exactly(2))->method('save');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('bookCourier')
            ->with(
                $this->anything(),
                ['DHL_SHIPMENT_1', 'DHL_SHIPMENT_2'],
                '2026-10-06',
                '10:00',
                '14:00',
                null
            )
            ->willReturn(new CourierBookingResult(true, ['ORDER_AH']));

        $service = new CourierBookingService(
            $client,
            $this->credentialsProviderStub,
            $repository,
            $this->timezoneMock,
            new ShipmentDateSanitizer($this->timezoneMock),
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new ShipmentDataPayloadReader(new Json())
        );

        $result = $service->bookCourier([10, 20], '2026-10-06', '10:00', '14:00');

        $this->assertTrue($result->isSuccess());
    }

    public function testBookCourierMapsOrderNumbersToFirstSeenServiceTypeGroups(): void
    {
        $shipments = [];
        foreach ([10 => 'AH', 20 => 'EK', 30 => 'AH'] as $entityId => $serviceType) {
            $shipment = $this->createMock(ShipmentDataInterface::class);
            $shipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_' . $entityId);
            $shipment->method('getShipmentData')->willReturn($this->shipmentPayload($serviceType));
            $shipment->expects($this->once())->method('setStatus');
            $shipment->expects($this->once())
                ->method('setDispatchNumber')
                ->with($serviceType === 'AH' ? 'ORDER_AH' : 'ORDER_EK');
            $shipments[$entityId] = $shipment;
        }

        $repository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $repository->expects($this->exactly(6))
            ->method('getById')
            ->willReturnMap([
                [10, $shipments[10]],
                [20, $shipments[20]],
                [30, $shipments[30]],
            ]);
        $repository->expects($this->exactly(3))->method('save');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('bookCourier')
            ->with(
                $this->anything(),
                ['DHL_SHIPMENT_10', 'DHL_SHIPMENT_20', 'DHL_SHIPMENT_30'],
                '2026-10-06',
                '10:00',
                '14:00',
                null
            )
            ->willReturn(new CourierBookingResult(true, ['ORDER_AH', 'ORDER_EK']));

        $service = new CourierBookingService(
            $client,
            $this->credentialsProviderStub,
            $repository,
            $this->timezoneMock,
            new ShipmentDateSanitizer($this->timezoneMock),
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new ShipmentDataPayloadReader(new Json())
        );

        $result = $service->bookCourier([10, 20, 30], '2026-10-06', '10:00', '14:00');

        $this->assertTrue($result->isSuccess());
    }

    public function testBookCourierStopsBeforeApiWhenServiceTypeIsMissing(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('DHL_SHIPMENT_1');
        $shipment->method('getShipmentData')->willReturn(null);

        $repository = $this->createStub(ShipmentDataRepositoryInterface::class);
        $repository->method('getById')->willReturn($shipment);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())->method('bookCourier');

        $service = new CourierBookingService(
            $client,
            $this->credentialsProviderStub,
            $repository,
            $this->timezoneMock,
            new ShipmentDateSanitizer($this->timezoneMock),
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new ShipmentDataPayloadReader(new Json())
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('service code');

        $service->bookCourier([10], '2026-10-06', '10:00', '14:00');
    }

    private function shipmentPayload(string $serviceType): string
    {
        return (new Json())->serialize(['shipmentSettings' => ['serviceType' => $serviceType]]);
    }
}
