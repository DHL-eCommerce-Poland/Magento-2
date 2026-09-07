<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CreateShipmentsResult;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\ShipmentFullDataMapper;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Ideo\Dhl\Model\Shipment\ShipmentDataValidator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class ShipmentCreationServiceTest extends TestCase
{
    /**
     * @var ShipmentDataService|MockObject
     */
    private ShipmentDataService|MockObject $shipmentDataServiceMock;

    /**
     * @var ShipmentDataValidator|MockObject
     */
    private ShipmentDataValidator|MockObject $validatorStub;

    /**
     * @var ShipmentFullDataMapper|MockObject
     */
    private ShipmentFullDataMapper|MockObject $mapperStub;

    /**
     * @var CredentialsProvider|MockObject
     */
    private CredentialsProvider|MockObject $credentialsProviderStub;

    /**
     * @var ClientInterface|MockObject
     */
    private ClientInterface|MockObject $clientStub;

    /**
     * @var LockManagerInterface|MockObject
     */
    private LockManagerInterface|MockObject $lockManagerMock;

    /**
     * @var ResourceConnection|MockObject
     */
    private ResourceConnection|MockObject $resourceConnectionStub;

    /**
     * @var AdapterInterface|MockObject
     */
    private AdapterInterface|MockObject $dbAdapterStub;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $loggerStub;

    /**
     * @var ShipmentCreationService
     */
    private ShipmentCreationService $service;

    protected function setUp(): void
    {
        $this->shipmentDataServiceMock = $this->createMock(ShipmentDataService::class);
        $this->validatorStub = $this->createStub(ShipmentDataValidator::class);
        $this->mapperStub = $this->createStub(ShipmentFullDataMapper::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->resourceConnectionStub = $this->createStub(ResourceConnection::class);
        $this->dbAdapterStub = $this->createStub(AdapterInterface::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->resourceConnectionStub->method('getConnection')->willReturn($this->dbAdapterStub);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();

        $returnBuilderMock = $this->createStub(\Ideo\Dhl\Model\Shipment\ReturnShipmentDataBuilder::class);
        $returnCreationServiceMock = $this->createStub(\Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService::class);
        $magentoSyncServiceMock = $this->createStub(\Ideo\Dhl\Model\Shipment\MagentoShipmentSyncService::class);

        $this->service = new ShipmentCreationService(
            $this->shipmentDataServiceMock,
            $this->validatorStub,
            $this->mapperStub,
            $this->credentialsProviderStub,
            $this->clientStub,
            $this->lockManagerMock,
            $this->loggerStub,
            $serializer,
            $returnBuilderMock,
            $returnCreationServiceMock,
            $magentoSyncServiceMock
        );
    }

    /**
     * Test createAndDispatchShipment saves DB record ONLY on API success.
     *
     * @return void
     */
    public function testCreateAndDispatchShipmentSuccessSavesDbRecord(): void
    {
        $shipmentData = $this->createStub(DhlShipmentData::class);
        $shipmentData->method('getOrderId')->willReturn(100);
        $shipmentData->method('toArray')->willReturn(['orderId' => 100]);

        $this->lockManagerMock->expects($this->once())
            ->method('lock')
            ->willReturn(true);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);
        $this->mapperStub->method('map')->willReturn(['content' => 'Test']);

        $apiResult = new CreateShipmentsResult(true, false, '90039521201', 'Nowa');
        $this->clientStub->method('createShipments')->willReturn($apiResult);

        // Expect DB save with status Nowa
        $this->shipmentDataServiceMock->expects($this->once())
            ->method('saveShipmentDataWithStatus')
            ->with($shipmentData, 'Nowa', null, '1', '2', '90039521201', null, ['90039521201']);

        $result = $this->service->createShipment($shipmentData, 'sandbox', '1', '2');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('90039521201', $result->getShipmentId());
    }

    /**
     * Test createShipment does NOT save DB record on API failure.
     *
     * @return void
     */
    public function testCreateShipmentFailureDoesNotSaveDbRecord(): void
    {
        $shipmentData = $this->createStub(DhlShipmentData::class);
        $shipmentData->method('getOrderId')->willReturn(100);
        $shipmentData->method('toArray')->willReturn(['orderId' => 100]);

        $this->lockManagerMock->expects($this->once())
            ->method('lock')
            ->willReturn(true);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);
        $this->mapperStub->method('map')->willReturn(['content' => 'Test']);

        $apiResult = new CreateShipmentsResult(false, false, null, null, [], 'ERR_POSTCODE', 'Błędny kod pocztowy');
        $this->clientStub->method('createShipments')->willReturn($apiResult);

        // Expect NO DB save on API error
        $this->shipmentDataServiceMock->expects($this->never())
            ->method('saveShipmentDataWithStatus');

        $result = $this->service->createShipment($shipmentData, 'sandbox', '1', '2');
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Błędny kod pocztowy', $result->getErrorMessage());
    }

    /**
     * Test successful shipment recreation.
     *
     * @return void
     */
    public function testRecreateShipmentSuccess(): void
    {
        $entityId = 123;
        $lockName = 'dhl_shipment_recreate_123';

        $this->lockManagerMock->expects($this->once())
            ->method('lock')
            ->with($lockName, 15)
            ->willReturn(true);

        $this->lockManagerMock->expects($this->once())
            ->method('unlock')
            ->with($lockName);

        $shipmentData = $this->createStub(DhlShipmentData::class);
        $entityStub = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $entityStub->method('getShipmentId')->willReturn('OLD_123');

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('getShipmentEntity')
            ->with($entityId)
            ->willReturn($entityStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);
        $this->mapperStub->method('map')->willReturn(['content' => 'Test']);

        $apiResult = new CreateShipmentsResult(true, false, '999888777');
        $this->clientStub->method('createShipments')->willReturn($apiResult);

        $result = $this->service->recreateShipment($entityId, $shipmentData, 'sandbox');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('999888777', $result->getShipmentId());
    }

    /**
     * Test auto-creation of return shipment when autoCreateReturnShipment is true.
     *
     * @return void
     */
    public function testCreateShipmentTriggersAutoReturnCreationWhenEnabled(): void
    {
        $shipmentSettings = $this->createStub(\Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData::class);
        $shipmentSettings->method('autoCreateReturnShipment')->willReturn(true);
        $shipmentSettings->method('getReturnLabelExpDays')->willReturn(14);

        $shipmentData = $this->createStub(DhlShipmentData::class);
        $shipmentData->method('getOrderId')->willReturn(100);
        $shipmentData->method('toArray')->willReturn(['orderId' => 100]);
        $shipmentData->method('getShipmentSettings')->willReturn($shipmentSettings);

        $this->lockManagerMock->method('lock')->willReturn(true);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);
        $this->mapperStub->method('map')->willReturn(['content' => 'Test']);

        $apiResult = new CreateShipmentsResult(true, false, '90039521201', 'Nowa');
        $this->clientStub->method('createShipments')->willReturn($apiResult);

        $savedEntityMock = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $savedEntityMock->method('getEntityId')->willReturn(42);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('saveShipmentDataWithStatus')
            ->willReturn($savedEntityMock);

        $returnBuilderMock = $this->createMock(\Ideo\Dhl\Model\Shipment\ReturnShipmentDataBuilder::class);
        $returnCreationServiceMock = $this->createMock(\Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService::class);

        $returnShipmentDataStub = $this->createStub(DhlShipmentData::class);
        $returnBuilderMock->expects($this->once())
            ->method('buildReturnShipmentData')
            ->with($shipmentData)
            ->willReturn($returnShipmentDataStub);

        $returnCreationServiceMock->expects($this->once())
            ->method('createReturnShipment')
            ->with(
                returnShipmentData: $returnShipmentDataStub,
                environment: 'sandbox',
                expirationDays: 14,
                primaryWaybillNumber: '90039521201',
                originalEntityId: 42
            );

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();
        $magentoSyncServiceMock = $this->createStub(\Ideo\Dhl\Model\Shipment\MagentoShipmentSyncService::class);
        $serviceWithReturn = new ShipmentCreationService(
            $this->shipmentDataServiceMock,
            $this->validatorStub,
            $this->mapperStub,
            $this->credentialsProviderStub,
            $this->clientStub,
            $this->lockManagerMock,
            $this->loggerStub,
            $serializer,
            $returnBuilderMock,
            $returnCreationServiceMock,
            $magentoSyncServiceMock
        );

        $result = $serviceWithReturn->createShipment($shipmentData, 'sandbox');
        $this->assertTrue($result->isSuccess());
    }
}
