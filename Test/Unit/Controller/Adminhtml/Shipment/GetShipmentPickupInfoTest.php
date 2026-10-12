<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Exception;
use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Controller\Adminhtml\Shipment\GetShipmentPickupInfo;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class GetShipmentPickupInfoTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private Context|MockObject $context;

    /**
     * @var RequestInterface|MockObject
     */
    private RequestInterface|MockObject $request;

    /**
     * @var JsonFactory|MockObject
     */
    private JsonFactory|MockObject $jsonFactory;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $jsonResult;

    /**
     * @var ShipmentDataRepositoryInterface|MockObject
     */
    private ShipmentDataRepositoryInterface|MockObject $shipmentDataRepository;

    /**
     * @var SerializerInterface|MockObject
     */
    private SerializerInterface|MockObject $serializer;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $logger;

    /**
     * @var GetShipmentPickupInfo
     */
    private GetShipmentPickupInfo $controller;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->context->method('getRequest')->willReturn($this->request);

        $this->jsonFactory = $this->createMock(JsonFactory::class);
        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);

        $this->shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new GetShipmentPickupInfo(
            $this->context,
            $this->jsonFactory,
            $this->shipmentDataRepository,
            $this->serializer,
            $this->logger
        );
    }

    public function testExecuteReturnsErrorWhenShipmentIdMissing(): void
    {
        $this->request->method('getParam')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'shipment_id' => 0,
                'shipment_ids' => [],
                default => $default,
            };
        });

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === false
                    && str_contains((string)$data['message'], 'Invalid shipment ID');
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }

    public function testExecuteReturnsSavedPickupInfoFromShipmentPayload(): void
    {
        $shipmentId = 2;
        $this->request->method('getParam')->willReturnCallback(function ($key, $default = null) use ($shipmentId) {
            return match ($key) {
                'shipment_id' => $shipmentId,
                'shipment_ids' => [$shipmentId],
                default => $default,
            };
        });

        $entity = $this->createMock(ShipmentDataInterface::class);
        $payloadArr = [
            'shipmentSettings' => [
                'shipmentDate' => '2026-10-05',
                'pickupHourFrom' => '13:00',
                'pickupHourTo' => '16:00',
            ],
        ];
        $jsonPayload = json_encode($payloadArr);
        $entity->method('getShipmentData')->willReturn($jsonPayload);

        $this->serializer->expects($this->once())
            ->method('unserialize')
            ->with($jsonPayload)
            ->willReturn($payloadArr);

        $this->shipmentDataRepository->expects($this->once())
            ->method('getById')
            ->with($shipmentId)
            ->willReturn($entity);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with([
                'success' => true,
                'data' => [
                    'pickup_time_from' => '13:00',
                    'pickup_time_to' => '16:00',
                ],
            ])
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }

    public function testExecuteReturnsNullPickupHoursWhenPayloadHasNoHours(): void
    {
        $shipmentId = 5;
        $this->request->method('getParam')->willReturnCallback(function ($key, $default = null) use ($shipmentId) {
            return match ($key) {
                'shipment_id' => $shipmentId,
                'shipment_ids' => [$shipmentId],
                default => $default,
            };
        });

        $entity = $this->createMock(ShipmentDataInterface::class);
        $payloadArr = ['shipmentSettings' => ['shipmentDate' => '2026-10-05']];
        $jsonPayload = json_encode($payloadArr);
        $entity->method('getShipmentData')->willReturn($jsonPayload);

        $this->serializer->expects($this->once())
            ->method('unserialize')
            ->with($jsonPayload)
            ->willReturn($payloadArr);

        $this->shipmentDataRepository->expects($this->once())
            ->method('getById')
            ->with($shipmentId)
            ->willReturn($entity);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with([
                'success' => true,
                'data' => [
                    'pickup_time_from' => null,
                    'pickup_time_to' => null,
                ],
            ])
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }

    public function testExecuteHandlesExceptionAndReturnsError(): void
    {
        $this->request->method('getParam')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'shipment_id' => 99,
                default => $default,
            };
        });

        $this->shipmentDataRepository->method('getById')
            ->willThrowException(new Exception('Shipment entity not found'));

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'Shipment entity not found',
            ])
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }
}
