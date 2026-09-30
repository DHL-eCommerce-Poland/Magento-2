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
    /**
     * @var MockObject
     */
    private MockObject $shipmentDataServiceMock;

    /**
     * @var MockObject
     */
    private MockObject $validatorMock;

    /**
     * @var MockObject
     */
    private MockObject $returnMapperMock;

    /**
     * @var MockObject
     */
    private MockObject $credentialsProviderMock;

    /**
     * @var MockObject
     */
    private MockObject $clientMock;

    /**
     * @var MockObject
     */
    private MockObject $lockManagerMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private mixed $loggerStub;

    /**
     * @var MockObject
     */
    private MockObject $serializerMock;

    /**
     * @var ReturnShipmentCreationService
     */
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

    public function testCreateReturnShipmentSingleSuccess(): void
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
            ->with('dhl_return_shipment_create_3003')
            ->willReturn(true);

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
            items: [
                [
                    'shipmentId' => 'RET3003',
                    'waybill' => 'RET3003',
                    'packageTrackingNumber' => 'P1',
                    'labelData' => 'pdf',
                    'labelMimeType' => 'application/pdf',
                ],
            ]
        );

        $this->clientMock->expects($this->once())
            ->method('createShipmentReturn')
            ->with($credentials, ['serviceType' => 'ZK'], 3003)
            ->willReturn($apiResult);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('updateReturnShipmentId')
            ->with(505, 'RET3003', $apiResult->getItems());

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

    public function testCreateReturnShipmentMultipleItemsSuccess(): void
    {
        $shipmentData = new DhlShipmentData(orderId: 4004);

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->willReturn('{"orderId":4004}');

        $this->lockManagerMock->expects($this->once())
            ->method('lock')
            ->with('dhl_return_shipment_create_4004', 15)
            ->willReturn(true);

        $this->lockManagerMock->expects($this->once())
            ->method('unlock')
            ->with('dhl_return_shipment_create_4004')
            ->willReturn(true);

        $this->validatorMock->expects($this->once())
            ->method('validate')
            ->with($shipmentData);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderMock->expects($this->once())
            ->method('getCredentials')
            ->willReturn($credentials);

        $this->returnMapperMock->expects($this->once())
            ->method('map')
            ->willReturn(['serviceType' => 'ZK']);

        $apiResult = new CreateShipmentReturnResult(
            success: true,
            timeout: false,
            items: [
                [
                    'shipmentId' => '90040025505',
                    'waybill' => '90040025505',
                    'packageTrackingNumber' => 'P1',
                    'labelData' => 'pdf1',
                    'labelMimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025516',
                    'waybill' => '90040025516',
                    'packageTrackingNumber' => 'P2',
                    'labelData' => 'pdf2',
                    'labelMimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025527',
                    'waybill' => '90040025527',
                    'packageTrackingNumber' => 'P3',
                    'labelData' => 'pdf3',
                    'labelMimeType' => 'application/pdf',
                ],
            ]
        );

        $this->clientMock->expects($this->once())
            ->method('createShipmentReturn')
            ->willReturn($apiResult);

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('updateReturnShipmentId')
            ->with(707, '90040025505, 90040025516, 90040025527', $apiResult->getItems());

        $result = $this->service->createReturnShipment(
            returnShipmentData: $shipmentData,
            environment: 'sandbox',
            expirationDays: 14,
            primaryWaybillNumber: 'ORIG_WAYBILL',
            originalEntityId: 707
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('90040025505, 90040025516, 90040025527', $result->getShipmentId());
    }
}
