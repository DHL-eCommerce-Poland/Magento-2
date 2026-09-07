<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CreateShipmentReturnResult;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\ShipmentReturnDataMapper;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Ideo\Dhl\Model\Shipment\ShipmentDataValidator;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReturnShipmentCreationServiceTest extends TestCase
{
    private ShipmentDataService|MockObject $shipmentDataServiceMock;
    private ShipmentDataValidator|MockObject $validatorMock;
    private ShipmentReturnDataMapper|MockObject $returnMapperMock;
    private CredentialsProvider|MockObject $credentialsProviderMock;
    private ClientInterface|MockObject $clientMock;
    private LockManagerInterface|MockObject $lockManagerMock;
    private LoggerInterface|MockObject $loggerStub;
    private SerializerInterface|MockObject $serializerMock;
    private ReturnShipmentCreationService $service;

    protected function setUp(): void
    {
        $this->shipmentDataServiceMock = $this->createMock(ShipmentDataService::class);
        $this->validatorMock = $this->createMock(ShipmentDataValidator::class);
        $this->returnMapperMock = $this->createMock(ShipmentReturnDataMapper::class);
        $this->credentialsProviderMock = $this->createMock(CredentialsProvider::class);
        $this->clientMock = $this->createMock(ClientInterface::class);
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        $this->service = new ReturnShipmentCreationService(
            $this->shipmentDataServiceMock,
            $this->validatorMock,
            $this->returnMapperMock,
            $this->credentialsProviderMock,
            $this->clientMock,
            $this->lockManagerMock,
            $this->loggerStub,
            $this->serializerMock
        );
    }

    public function testCreateReturnShipmentSuccess(): void
    {
        $shipmentData = new DhlShipmentData(orderId: 3003);

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->willReturn('{"orderId":3003}');

        $this->lockManagerMock->expects($this->once())
            ->method('lock')
            ->with('dhl_return_shipment_create_3003', 15)
            ->willReturn(true);

        $this->lockManagerMock->expects($this->once())
            ->method('unlock')
            ->with('dhl_return_shipment_create_3003');

        $this->validatorMock->expects($this->once())
            ->method('validate')
            ->with($shipmentData);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->with('sandbox')
            ->willReturn($credentials);

        $this->returnMapperMock->expects($this->once())
            ->method('map')
            ->with($shipmentData, 14, 'WAYBILL_ORIG')
            ->willReturn(['serviceType' => 'ZK']);

        $apiResult = new CreateShipmentReturnResult(
            success: true,
            timeout: false,
            shipmentId: 'RET3003',
            waybill: 'RET3003',
            labelData: 'pdfdata'
        );

        $this->clientMock->expects($this->once())
            ->method('createShipmentReturn')
            ->with($credentials, ['serviceType' => 'ZK'], 3003)
            ->willReturn($apiResult);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('updateReturnShipmentId')
            ->with(505, 'RET3003');

        $result = $this->service->createReturnShipment(
            returnShipmentData: $shipmentData,
            environment: 'sandbox',
            expirationDays: 14,
            primaryWaybillNumber: 'WAYBILL_ORIG',
            originalEntityId: 505
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('RET3003', $result->getShipmentId());
    }
}
