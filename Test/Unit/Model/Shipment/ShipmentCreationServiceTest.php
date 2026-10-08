<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CreateShipmentResult;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\ShipmentFullDataMapper;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader;
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
    private $shipmentDataServiceMock;

    /**
     * @var ShipmentDataValidator|MockObject
     */
    private $validatorStub;

    /**
     * @var ShipmentFullDataMapper|MockObject
     */
    private $mapperStub;

    /**
     * @var CredentialsProvider|MockObject
     */
    private $credentialsProviderStub;

    /**
     * @var ClientInterface|MockObject
     */
    private $clientStub;

    /**
     * @var LockManagerInterface|MockObject
     */
    private $lockManagerMock;

    /**
     * @var ResourceConnection|MockObject
     */
    private $resourceConnectionStub;

    /**
     * @var AdapterInterface|MockObject
     */
    private $dbAdapterStub;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerStub;

    /**
     * @var ShipmentDataPayloadReader
     */
    private ShipmentDataPayloadReader $payloadReaderStub;

    /**
     * @var ShipmentCreationService
     */
    private $service;

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
        $this->payloadReaderStub = new \Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader($serializer);

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
            $magentoSyncServiceMock,
            $this->payloadReaderStub
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

        $apiResult = new CreateShipmentResult(true, false, '90039521201', 'Nowa');
        $this->clientStub->method('createShipment')->willReturn($apiResult);

        // Expect DB save with status Nowa
        $savedEntityStub = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $savedEntityStub->method('getEntityId')->willReturn(55);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('saveShipmentDataWithStatus')
            ->with($shipmentData, 'Nowa', '90039521201', null, [])
            ->willReturn($savedEntityStub);

        $result = $this->service->createShipment($shipmentData, 'sandbox');
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('90039521201', $result->getShipmentId());
        $this->assertEquals(55, $result->getEntityId());
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

        $apiResult = new CreateShipmentResult(false, false, null, null, [], 'ERR_POSTCODE', 'Błędny kod pocztowy');
        $this->clientStub->method('createShipment')->willReturn($apiResult);

        // Expect NO DB save on API error
        $this->shipmentDataServiceMock->expects($this->never())
            ->method('saveShipmentDataWithStatus');

        $result = $this->service->createShipment($shipmentData, 'sandbox');
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

        $deleteResult = new \Ideo\Dhl\Model\Api\DeleteShipmentResult(true, 'OLD_123');
        $this->clientStub->method('deleteShipment')->willReturn($deleteResult);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('clearShipmentId')
            ->with($entityId);

        $apiResult = new CreateShipmentResult(true, false, '999888777');
        $this->clientStub->method('createShipment')->willReturn($apiResult);

        $result = $this->service->recreateShipment($entityId, $shipmentData, 'sandbox');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('999888777', $result->getShipmentId());
        $this->assertEquals(123, $result->getEntityId());
    }

    /**
     * Test recreateShipment clears old shipment ID when delete succeeds in DHL but createShipment fails.
     *
     * @return void
     */
    public function testRecreateShipmentClearsOldIdWhenDeleteSucceedsButCreateFails(): void
    {
        $entityId = 124;
        $lockName = 'dhl_shipment_recreate_124';

        $this->lockManagerMock->method('lock')->willReturn(true);

        $shipmentData = $this->createStub(DhlShipmentData::class);
        $entityStub = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $entityStub->method('getShipmentId')->willReturn('OLD_456');

        $this->shipmentDataServiceMock->method('getShipmentEntity')->willReturn($entityStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $deleteResult = new \Ideo\Dhl\Model\Api\DeleteShipmentResult(true, 'OLD_456');
        $this->clientStub->method('deleteShipment')->willReturn($deleteResult);

        // Expect clearShipmentId to be called so old ID is removed from DB despite createShipment failing
        $this->shipmentDataServiceMock->expects($this->once())
            ->method('clearShipmentId')
            ->with($entityId);

        $failedCreateResult = new CreateShipmentResult(
            false,
            false,
            null,
            null,
            [],
            '106',
            'Nie odnaleziono wybranego punktu Parelstation'
        );
        $this->clientStub->method('createShipment')->willReturn($failedCreateResult);

        $result = $this->service->recreateShipment($entityId, $shipmentData, 'sandbox');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Nie odnaleziono wybranego punktu Parelstation', $result->getErrorMessage());
    }

    /**
     * Test recreateShipment does not clear old ID when deleteShipment API call fails.
     *
     * @return void
     */
    public function testRecreateShipmentDoesNotClearIdWhenDeleteFails(): void
    {
        $entityId = 125;
        $this->lockManagerMock->method('lock')->willReturn(true);

        $shipmentData = $this->createStub(DhlShipmentData::class);
        $entityStub = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $entityStub->method('getShipmentId')->willReturn('OLD_789');

        $this->shipmentDataServiceMock->method('getShipmentEntity')->willReturn($entityStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $deleteResult = new \Ideo\Dhl\Model\Api\DeleteShipmentResult(false, 'OLD_789', 'ERR', 'Auth error');
        $this->clientStub->method('deleteShipment')->willReturn($deleteResult);

        // Expect clearShipmentId NEVER to be called when delete failed
        $this->shipmentDataServiceMock->expects($this->never())
            ->method('clearShipmentId');

        $apiResult = new CreateShipmentResult(true, false, 'NEW_999');
        $this->clientStub->method('createShipment')->willReturn($apiResult);

        $result = $this->service->recreateShipment($entityId, $shipmentData, 'sandbox');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('NEW_999', $result->getShipmentId());
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

        $apiResult = new CreateShipmentResult(true, false, '90039521201', 'Nowa');
        $this->clientStub->method('createShipment')->willReturn($apiResult);

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
            $magentoSyncServiceMock,
            $this->payloadReaderStub
        );

        $result = $serviceWithReturn->createShipment($shipmentData, 'sandbox');
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test auto-creation of return shipment when recreateShipment is executed with autoCreateReturnShipment enabled.
     *
     * @return void
     */
    public function testRecreateShipmentTriggersAutoReturnCreationWhenEnabled(): void
    {
        $shipmentSettings = $this->createStub(\Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData::class);
        $shipmentSettings->method('autoCreateReturnShipment')->willReturn(true);
        $shipmentSettings->method('getReturnLabelExpDays')->willReturn(14);

        $shipmentData = $this->createStub(DhlShipmentData::class);
        $shipmentData->method('getOrderId')->willReturn(100);
        $shipmentData->method('toArray')->willReturn(['orderId' => 100]);
        $shipmentData->method('getShipmentSettings')->willReturn($shipmentSettings);

        $this->lockManagerMock->method('lock')->willReturn(true);

        $existingEntityMock = $this->createMock(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $existingEntityMock->method('getShipmentId')->willReturn('90039521000');
        $existingEntityMock->method('getMagentoShipmentId')->willReturn(null);
        $this->shipmentDataServiceMock->method('getShipmentEntity')->willReturn($existingEntityMock);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);
        $this->mapperStub->method('map')->willReturn(['content' => 'Test']);

        $apiResult = new CreateShipmentResult(true, false, '90039521202', 'Nowa');
        $this->clientStub->method('createShipment')->willReturn($apiResult);

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
                $returnShipmentDataStub,
                'sandbox',
                14,
                '90039521202',
                10
            );

        $serializer = $this->createStub(\Magento\Framework\Serialize\SerializerInterface::class);
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
            $magentoSyncServiceMock,
            $this->payloadReaderStub
        );

        $result = $serviceWithReturn->recreateShipment(10, $shipmentData, 'sandbox');
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test createShipment throws LocalizedException when a shipment has already been created for the order.
     *
     * @return void
     */
    public function testCreateShipmentThrowsExceptionWhenShipmentAlreadyExists(): void
    {
        $shipmentData = $this->createStub(DhlShipmentData::class);
        $shipmentData->method('getOrderId')->willReturn(100);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('hasShipmentForOrder')
            ->with(100)
            ->willReturn(true);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('A DHL shipment has already been created for order #100.');

        $this->service->createShipment($shipmentData, 'sandbox');
    }

    /**
     * Test createShipment calls getPieceId when createShipment returns empty packageNumbers array.
     *
     * @return void
     */
    public function testCreateShipmentCallsGetPieceIdWhenPackageNumbersEmpty(): void
    {
        $shipmentSettings = new \Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData(autoCreateReturnShipment: false);
        $shipmentData = new DhlShipmentData(orderId: 300, shipmentSettings: $shipmentSettings);

        $this->shipmentDataServiceMock->method('hasShipmentForOrder')->willReturn(false);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $apiResult = new CreateShipmentResult(true, false, '90040027827', 'Utworzono', []);
        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('createShipment')
            ->willReturn($apiResult);
        $clientMock->expects($this->once())
            ->method('getPieceId')
            ->with($credentials, '90040027827')
            ->willReturn(['JJD000030025688110000001106', 'JJD000030025688110000001117']);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('saveShipmentDataWithStatus')
            ->with(
                $shipmentData,
                'Utworzono',
                '90040027827',
                null,
                ['JJD000030025688110000001106', 'JJD000030025688110000001117'],
                null
            );

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();
        $returnBuilderMock = $this->createStub(\Ideo\Dhl\Model\Shipment\ReturnShipmentDataBuilder::class);
        $returnCreationServiceMock = $this->createStub(\Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService::class);
        $magentoSyncServiceMock = $this->createStub(\Ideo\Dhl\Model\Shipment\MagentoShipmentSyncService::class);
        $lockManagerStub = $this->createStub(LockManagerInterface::class);
        $lockManagerStub->method('lock')->willReturn(true);
        $lockManagerStub->method('unlock')->willReturn(true);

        $serviceWithMockClient = new ShipmentCreationService(
            $this->shipmentDataServiceMock,
            $this->validatorStub,
            $mapperMock ?? $this->mapperStub,
            $this->credentialsProviderStub,
            $clientMock,
            $lockManagerStub,
            $this->loggerStub,
            $serializer,
            $returnBuilderMock,
            $returnCreationServiceMock,
            $magentoSyncServiceMock,
            $this->payloadReaderStub
        );

        $result = $serviceWithMockClient->createShipment($shipmentData, 'sandbox');
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test recreateShipment passes return shipment IDs to deleteShipment when shipment has returns.
     *
     * @return void
     */
    public function testRecreateShipmentWithReturnPackagesPassesReturnIdsToDeleteShipment(): void
    {
        $entityId = 126;
        $this->lockManagerMock->method('lock')->willReturn(true);

        $shipmentData = $this->createStub(DhlShipmentData::class);
        $entityStub = $this->createStub(\Ideo\Dhl\Api\Data\ShipmentDataInterface::class);
        $entityStub->method('getShipmentId')->willReturn('OLD_123');
        $entityStub->method('getReturnShipmentId')->willReturn('90040146098, 90040146099');

        $this->shipmentDataServiceMock->method('getShipmentEntity')->willReturn($entityStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);
        $this->mapperStub->method('map')->willReturn(['content' => 'Test']);

        $deleteResult = new \Ideo\Dhl\Model\Api\DeleteShipmentResult(true, 'OLD_123');
        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('deleteShipment')
            ->with($credentials, 'OLD_123', ['90040146098', '90040146099'])
            ->willReturn($deleteResult);

        $apiResult = new CreateShipmentResult(true, false, '999888777');
        $clientMock->method('createShipment')->willReturn($apiResult);

        $serializer = new \Magento\Framework\Serialize\Serializer\Json();
        $service = new ShipmentCreationService(
            $this->shipmentDataServiceMock,
            $this->validatorStub,
            $this->mapperStub,
            $this->credentialsProviderStub,
            $clientMock,
            $this->lockManagerMock,
            $this->loggerStub,
            $serializer,
            $this->createStub(\Ideo\Dhl\Model\Shipment\ReturnShipmentDataBuilder::class),
            $this->createStub(\Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService::class),
            $this->createStub(\Ideo\Dhl\Model\Shipment\MagentoShipmentSyncService::class),
            new \Ideo\Dhl\Model\Shipment\ShipmentDataPayloadReader($serializer)
        );

        $result = $service->recreateShipment($entityId, $shipmentData, 'sandbox');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('999888777', $result->getShipmentId());
    }
}
