<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use DhlPoland\Dhl\Model\Api\DeleteShipmentResult;
use DhlPoland\Dhl\Model\Shipment\ShipmentDataPayloadReader;
use DhlPoland\Dhl\Model\Shipment\ShipmentDeletionService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class ShipmentDeletionServiceTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $repositoryMock;
    /**
     * @var MockObject
     */
    private $clientMock;
    /**
     * @var MockObject
     */
    private $credentialsProviderMock;
    /**
     * @var MockObject
     */
    private $loggerMock;
    /**
     * @var ShipmentDataPayloadReader
     */
    private ShipmentDataPayloadReader $payloadReaderStub;
    /**
     * @var ShipmentDeletionService
     */
    private $deletionService;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(ShipmentDataRepositoryInterface::class);
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->credentialsProviderMock = $this->createMock(CredentialsProvider::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->payloadReaderStub = new ShipmentDataPayloadReader(new Json());
        $syncServiceMock = $this->createStub(\DhlPoland\Dhl\Model\Shipment\MagentoShipmentSyncService::class);

        $this->deletionService = new ShipmentDeletionService(
            $this->repositoryMock,
            $this->clientMock,
            $this->credentialsProviderMock,
            $syncServiceMock,
            $this->loggerMock,
            $this->payloadReaderStub
        );
    }

    public function testDeleteShipmentSuccess(): void
    {
        $entityId = 123;
        $shipmentId = '1234567890';
        $credentials = new Credentials('user', 'pass', true);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn($shipmentId);

        $this->repositoryMock->expects($this->once())
            ->method('getById')
            ->with($entityId)
            ->willReturn($shipmentMock);

        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->willReturn($credentials);

        $deleteResult = new DeleteShipmentResult(true, $shipmentId);
        $this->clientMock->expects($this->once())
            ->method('deleteShipment')
            ->with($credentials, $shipmentId, [])
            ->willReturn($deleteResult);

        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with($shipmentMock)
            ->willReturn(true);

        $result = $this->deletionService->deleteShipment($entityId);
        $this->assertTrue($result->isSuccess());
    }

    public function testDeleteShipmentWithReturnPackagesPassesReturnIdsToClient(): void
    {
        $entityId = 125;
        $shipmentId = '1234567890';
        $credentials = new Credentials('user', 'pass', true);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn($shipmentId);
        $shipmentMock->method('getReturnShipmentId')
            ->willReturn('90040146098, 90040146099');

        $this->repositoryMock->expects($this->once())
            ->method('getById')
            ->with($entityId)
            ->willReturn($shipmentMock);

        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->willReturn($credentials);

        $deleteResult = new DeleteShipmentResult(true, $shipmentId);
        $this->clientMock->expects($this->once())
            ->method('deleteShipment')
            ->with($credentials, $shipmentId, ['90040146098', '90040146099'])
            ->willReturn($deleteResult);

        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with($shipmentMock)
            ->willReturn(true);

        $result = $this->deletionService->deleteShipment($entityId);
        $this->assertTrue($result->isSuccess());
    }

    public function testDeleteShipmentApiFailureReturnsReasonWithoutErrorLogging(): void
    {
        $entityId = 123;
        $shipmentId = '1234567890';
        $credentials = new Credentials('user', 'pass', false);

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn($shipmentId);

        $this->repositoryMock->expects($this->once())
            ->method('getById')
            ->with($entityId)
            ->willReturn($shipmentMock);

        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->willReturn($credentials);

        $deleteResult = new DeleteShipmentResult(false, $shipmentId, 'ERR', 'Shipment cannot be deleted');
        $this->clientMock->expects($this->once())
            ->method('deleteShipment')
            ->with($credentials, $shipmentId, [])
            ->willReturn($deleteResult);

        $this->repositoryMock->expects($this->never())->method('delete');
        $this->loggerMock->expects($this->never())->method('error');

        $result = $this->deletionService->deleteShipment($entityId);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Shipment cannot be deleted', $result->getErrorMessage());
    }

    public function testDeleteShipmentCallsCancelDhlTrackingWhenMagentoShipmentIdExists(): void
    {
        $entityId = 456;
        $shipmentId = 'DHL999';
        $magentoShipmentId = 77;
        $credentials = new Credentials('user', 'pass', true);

        $repositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);
        $clientStub = $this->createStub(ClientInterface::class);
        $credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $syncServiceMock = $this->createMock(\DhlPoland\Dhl\Model\Shipment\MagentoShipmentSyncService::class);

        $service = new ShipmentDeletionService(
            $repositoryStub,
            $clientStub,
            $credentialsProviderStub,
            $syncServiceMock,
            $this->loggerMock,
            $this->payloadReaderStub
        );

        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getShipmentId')->willReturn($shipmentId);
        $shipmentStub->method('getMagentoShipmentId')->willReturn($magentoShipmentId);

        $repositoryStub->method('getById')->willReturn($shipmentStub);
        $credentialsProviderStub->method('getCredentials')->willReturn($credentials);
        $clientStub->method('deleteShipment')->willReturn(new DeleteShipmentResult(true, $shipmentId));

        $syncServiceMock->expects($this->once())
            ->method('cancelDhlTracking')
            ->with($magentoShipmentId);

        $repositoryStub->method('delete')->willReturn(true);

        $result = $service->deleteShipment($entityId);
        $this->assertTrue($result->isSuccess());
    }

    public function testDeleteShipmentWhenShipmentIdIsNullDeletesFromDbWithoutApiCall(): void
    {
        $entityId = 555;
        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->expects($this->once())
            ->method('getShipmentId')
            ->willReturn(null);

        $this->repositoryMock->expects($this->once())
            ->method('getById')
            ->with($entityId)
            ->willReturn($shipmentMock);

        // Expect client deleteShipment NEVER to be called since shipmentId is null
        $this->clientMock->expects($this->never())
            ->method('deleteShipment');

        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with($shipmentMock)
            ->willReturn(true);

        $result = $this->deletionService->deleteShipment($entityId);
        $this->assertTrue($result->isSuccess());
    }
}
