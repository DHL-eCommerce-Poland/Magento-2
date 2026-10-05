<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\BookCourier;
use Ideo\Dhl\Model\Api\CourierBookingResult;
use Ideo\Dhl\Model\Shipment\CourierBookingService;
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
    private Context|MockObject $context;
    private RequestInterface|MockObject $request;
    private ManagerInterface|MockObject $messageManager;
    private JsonFactory|MockObject $jsonFactory;
    private Json|MockObject $jsonResult;
    private CourierBookingService|MockObject $bookingService;
    private ScopeConfigInterface|MockObject $scopeConfig;
    private LoggerInterface|MockObject $logger;
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
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new BookCourier(
            $this->context,
            $this->jsonFactory,
            $this->bookingService,
            $this->scopeConfig,
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

        $this->scopeConfig->method('getValue')->willReturn('sandbox');

        $bookingResult = $this->createMock(CourierBookingResult::class);
        $bookingResult->method('isSuccess')->willReturn(true);
        $bookingResult->method('getCourierOrderId')->willReturn('ORDER999');

        $this->bookingService->expects($this->once())
            ->method('bookCourier')
            ->with([123], '2026-10-05', '10:00', '14:00', 'Test info', 'sandbox')
            ->willReturn($bookingResult);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage');

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === true
                    && $data['courier_order_id'] === 'ORDER999';
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
