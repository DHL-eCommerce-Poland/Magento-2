<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\TrackingResult;
use Ideo\Dhl\Model\Shipment\ShipmentTrackingService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ShipmentTrackingServiceTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $shipmentDataRepositoryMock;
    /**
     * @var Stub
     */
    private $clientStub;
    /**
     * @var Stub
     */
    private $credentialsProviderStub;
    /**
     * @var ShipmentTrackingService
     */
    private $service;

    protected function setUp(): void
    {
        $this->shipmentDataRepositoryMock = $this->createMock(ShipmentDataRepositoryInterface::class);
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);

        $this->service = new ShipmentTrackingService(
            $this->shipmentDataRepositoryMock,
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );
    }

    public function testUpdateStatusSuccess(): void
    {
        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn('1234567890');

        $shipmentMock->expects($this->once())
            ->method('setStatus')
            ->with('Doręczono');

        $this->shipmentDataRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($shipmentMock);

        $this->shipmentDataRepositoryMock->expects($this->once())
            ->method('save')
            ->with($shipmentMock);

        $credentials = new Credentials('user', 'pass', true);

        $this->credentialsProviderStub
            ->method('getCredentials')
            ->willReturn($credentials);

        $trackingResult = new TrackingResult(
            success: true,
            shipmentId: '1234567890',
            receivedBy: 'Jan Kowalski',
            events: [
                [
                    'status' => 'DOR',
                    'description' => 'Doręczono',
                    'terminal' => 'Warszawa',
                    'timestamp' => '2026-08-28 14:00:00',
                ],
            ]
        );

        $this->clientStub
            ->method('getTrackAndTraceInfo')
            ->willReturn($trackingResult);

        $result = $this->service->updateStatus(1);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Doręczono', $result->getLatestStatusDescription());
    }

    public function testUpdateStatusThrowsExceptionWhenNoShipmentId(): void
    {
        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn(null);

        $this->shipmentDataRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($shipmentMock);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No DHL shipment ID available for this shipment entity.');

        $this->service->updateStatus(1);
    }
}
