<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\Prepare;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Ideo\Dhl\Model\Template\TemplateManager;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PrepareTest extends TestCase
{
    private $context;
    private $request;
    private $orderRepository;
    private $templateManager;
    private $jsonFactory;
    private $jsonResult;
    private $shipmentDataService;
    private $controller;

    protected function setUp(): void
    {
        $this->context = $this->createStub(Context::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->context->method('getRequest')->willReturn($this->request);

        $this->orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $this->templateManager = $this->createMock(TemplateManager::class);
        $this->jsonFactory = $this->createStub(JsonFactory::class);
        $this->jsonResult = $this->createMock(Json::class);
        $this->jsonFactory->method('create')->willReturn($this->jsonResult);
        $this->shipmentDataService = $this->createMock(ShipmentDataService::class);

        $this->controller = new Prepare(
            $this->context,
            $this->orderRepository,
            $this->templateManager,
            $this->jsonFactory,
            $this->shipmentDataService
        );
    }

    public function testExecuteReturnsErrorWhenShipmentAlreadyExists(): void
    {
        $this->request->method('getParam')->willReturnCallback(fn ($key) => match ($key) {
            'order_id' => 101,
            default => null,
        });

        $this->shipmentDataService->expects($this->once())
            ->method('hasShipmentForOrder')
            ->with(101)
            ->willReturn(true);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with([
                'success' => false,
                'message' => 'A DHL shipment has already been created for order #101.',
            ])
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }

    public function testExecuteReturnsSuccessWhenNoShipmentExists(): void
    {
        $this->request->method('getParam')->willReturnCallback(fn ($key) => match ($key) {
            'order_id' => 102,
            default => null,
        });

        $this->shipmentDataService->expects($this->once())
            ->method('hasShipmentForOrder')
            ->with(102)
            ->willReturn(false);

        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn('dhlparcel_parcel_polska');
        $this->orderRepository->method('get')->willReturn($order);

        $this->templateManager->expects($this->once())
            ->method('getShipmentTemplatesOptions')
            ->with('dhlparcel_parcel_polska')
            ->willReturn([['value' => 1, 'label' => 'Template Polska']]);

        $shipmentData = new DhlShipmentData(orderId: 102);
        $this->shipmentDataService->method('prepareShipmentData')->willReturn($shipmentData);

        $this->templateManager->expects($this->once())
            ->method('getCustomsTemplatesOptions')
            ->willReturn([['value' => 5, 'label' => 'Customs Template 1']]);

        $this->jsonResult->expects($this->once())
            ->method('setData')
            ->with(self::callback(function ($data) {
                return is_array($data)
                    && ($data['success'] ?? false) === true
                    && ($data['shipmentTemplates'] ?? []) === [['value' => 1, 'label' => 'Template Polska']]
                    && ($data['customsTemplates'] ?? []) === [['value' => 5, 'label' => 'Customs Template 1']];
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResult, $result);
    }
}
