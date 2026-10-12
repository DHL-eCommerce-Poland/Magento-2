<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Controller\Adminhtml\Shipment\BookCourier;
use DhlPoland\Dhl\Model\Api\CourierBookingResult;
use DhlPoland\Dhl\Model\Shipment\CourierBookingService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class BookCourierTest extends TestCase
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
     * @var ManagerInterface|MockObject
     */
    private ManagerInterface|MockObject $messageManager;

    /**
     * @var JsonFactory|MockObject
     */
    private JsonFactory|MockObject $jsonFactory;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $jsonResult;

    /**
     * @var CourierBookingService|MockObject
     */
    private CourierBookingService|MockObject $bookingService;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $logger;

    /**
     * @var BookCourier
     */
    private BookCourier $controller;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);

        $this->context->method('getRequest')->willReturn($this->request);
        $this->context->method('getMessageManager')->willReturn($this->messageManager);

        $this->jsonFactory = $this->createMock(JsonFactory::class);
        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);

        $this->bookingService = $this->createMock(CourierBookingService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new BookCourier(
            $this->context,
            $this->jsonFactory,
            $this->bookingService,
            $this->logger
        );
    }

    public function testExecuteSuccessAddsMessageToManagerAndReturnsJson(): void
    {
        $this->request->method('getParam')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'shipment_ids' => [123],
                'pickup_date' => '2026-10-05',
                'pickup_time_from' => '10:00',
                'pickup_time_to' => '14:00',
                'additional_info' => 'Test info',
                default => $default,
            };
        });

        $bookingResult = $this->createMock(CourierBookingResult::class);
        $bookingResult->method('isSuccess')->willReturn(true);
        $bookingResult->method('getCourierOrderIds')->willReturn(['ORDER999']);

        $this->bookingService->expects($this->once())
            ->method('bookCourier')
            ->with([123], '2026-10-05', '10:00', '14:00', 'Test info')
            ->willReturn($bookingResult);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage');

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === true
                    && $data['courier_order_ids'] === ['ORDER999'];
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }

    /**
     * Test that the success message and JSON response include every courier order number.
     *
     * @return void
     */
    public function testExecuteSuccessReportsEveryCourierOrderNumber(): void
    {
        $this->request->method('getParam')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'shipment_ids' => [123, 456],
                'pickup_date' => '2026-10-05',
                'pickup_time_from' => '10:00',
                'pickup_time_to' => '14:00',
                'additional_info' => '',
                default => $default,
            };
        });

        $bookingResult = $this->createMock(CourierBookingResult::class);
        $bookingResult->method('isSuccess')->willReturn(true);
        $bookingResult->method('getCourierOrderIds')->willReturn(['ORDER_1', 'ORDER_2']);

        $this->bookingService->expects($this->once())
            ->method('bookCourier')
            ->with([123, 456], '2026-10-05', '10:00', '14:00', '')
            ->willReturn($bookingResult);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with($this->callback(static function ($message): bool {
                return (string) $message === 'Courier booked successfully. Courier order numbers: ORDER_1, ORDER_2';
            }));

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(static function (array $data): bool {
                return $data['success'] === true
                    && $data['courier_order_ids'] === ['ORDER_1', 'ORDER_2'];
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }

    public function testExecuteErrorWhenMissingShipmentIds(): void
    {
        $this->request->method('getParam')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'shipment_ids' => [],
                default => $default,
            };
        });

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === false;
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }
}
