<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\DeleteShipmentResult;
use Ideo\Dhl\Model\Shipment\ShipmentDeletionService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ShipmentDeletionServiceTest extends TestCase
{
    private ShipmentDataRepositoryInterface|MockObject $repositoryMock;
    private ClientInterface|MockObject $clientMock;
    private CredentialsProvider|MockObject $credentialsProviderMock;
    private ScopeConfigInterface|MockObject $scopeConfigMock;
    private LoggerInterface|MockObject $loggerMock;
    private ShipmentDeletionService $deletionService;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(ShipmentDataRepositoryInterface::class);
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->credentialsProviderMock = $this->createMock(CredentialsProvider::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->loggerMock = $this->createStub(LoggerInterface::class);

        $this->deletionService = new ShipmentDeletionService(
            $this->repositoryMock,
            $this->clientMock,
            $this->credentialsProviderMock,
            $this->scopeConfigMock,
            $this->loggerMock
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

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('carriers/dhlparcel/sandbox')
            ->willReturn(1);

        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->with('sandbox')
            ->willReturn($credentials);

        $deleteResult = new DeleteShipmentResult(true, $shipmentId);
        $this->clientMock->expects($this->once())
            ->method('deleteShipment')
            ->with($credentials, $shipmentId)
            ->willReturn($deleteResult);

        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with($shipmentMock)
            ->willReturn(true);

        $result = $this->deletionService->deleteShipment($entityId);
        $this->assertTrue($result);
    }

    public function testDeleteShipmentApiFailureThrowsException(): void
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

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('carriers/dhlparcel/sandbox')
            ->willReturn(0);

        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->with('production')
            ->willReturn($credentials);

        $deleteResult = new DeleteShipmentResult(false, $shipmentId, 'ERR', 'Shipment cannot be deleted');
        $this->clientMock->expects($this->once())
            ->method('deleteShipment')
            ->with($credentials, $shipmentId)
            ->willReturn($deleteResult);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to delete shipment in DHL: Shipment cannot be deleted');

        $this->deletionService->deleteShipment($entityId);
    }

    public function testDeleteShipmentCallsCancelDhlTrackingWhenMagentoShipmentIdExists(): void
    {
        $entityId = 456;
        $shipmentId = 'DHL999';
        $magentoShipmentId = 77;
        $credentials = new Credentials('user', 'pass', true);

        $syncServiceMock = $this->createMock(\Ideo\Dhl\Model\Shipment\MagentoShipmentSyncService::class);
        $service = new ShipmentDeletionService(
            $this->repositoryMock,
            $this->clientMock,
            $this->credentialsProviderMock,
            $this->scopeConfigMock,
            $this->loggerMock,
            $syncServiceMock
        );

        $shipmentMock = $this->createMock(ShipmentDataInterface::class);
        $shipmentMock->method('getShipmentId')->willReturn($shipmentId);
        $shipmentMock->method('getMagentoShipmentId')->willReturn($magentoShipmentId);

        $this->repositoryMock->method('getById')->with($entityId)->willReturn($shipmentMock);
        $this->scopeConfigMock->method('getValue')->willReturn(1);
        $this->credentialsProviderMock->method('getCredentials')->willReturn($credentials);
        $this->clientMock->method('deleteShipment')->willReturn(new DeleteShipmentResult(true, $shipmentId));

        $syncServiceMock->expects($this->once())
            ->method('cancelDhlTracking')
            ->with($magentoShipmentId);

        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with($shipmentMock)
            ->willReturn(true);

        $result = $service->deleteShipment($entityId);
        $this->assertTrue($result);
    }
}
