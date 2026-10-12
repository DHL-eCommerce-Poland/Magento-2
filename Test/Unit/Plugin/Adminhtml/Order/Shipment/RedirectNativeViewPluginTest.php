<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Plugin\Adminhtml\Order\Shipment;

use Exception;
use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Plugin\Adminhtml\Order\Shipment\RedirectNativeViewPlugin;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Message\Collection as MessageCollection;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Shipment\View as NativeShipmentViewController;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class RedirectNativeViewPluginTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $request;
    /**
     * @var MockObject
     */
    private $response;
    /**
     * @var MockObject
     */
    private $urlBuilder;
    /**
     * @var MockObject
     */
    private $shipmentDataRepository;
    /**
     * @var MockObject
     */
    private $orderRepository;
    /**
     * @var MockObject
     */
    private $shipmentRepository;
    /**
     * @var MockObject
     */
    private $messageManager;
    /**
     * @var MockObject
     */
    private $logger;
    /**
     * @var RedirectNativeViewPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(HttpResponse::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->shipmentDataRepository = $this->createMock(ShipmentDataRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->plugin = new RedirectNativeViewPlugin(
            $this->request,
            $this->response,
            $this->urlBuilder,
            $this->shipmentDataRepository,
            $this->orderRepository,
            $this->shipmentRepository,
            $this->messageManager,
            $this->logger
        );
    }

    public function testAroundExecuteProceedsWhenNoShipmentIdInRequest(): void
    {
        $this->request->method('getParam')->willReturn(null);
        $subject = $this->createMock(NativeShipmentViewController::class);
        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return 'native_result';
        };

        $result = $this->plugin->aroundExecute($subject, $proceed);

        $this->assertTrue($proceedCalled);
        $this->assertEquals('native_result', $result);
    }

    public function testAroundExecuteRedirectsToDhlEditPageWhenDhlShipmentExists(): void
    {
        $this->request->method('getParam')->with('shipment_id')->willReturn('10');

        $dhlShipment = $this->createMock(ShipmentDataInterface::class);
        $dhlShipment->method('getEntityId')->willReturn(3);

        $this->shipmentDataRepository->expects($this->once())
            ->method('getByMagentoShipmentId')
            ->with(10)
            ->willReturn($dhlShipment);

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('dhlpoland/shipment/edit', ['entity_id' => 3])
            ->willReturn('http://example.com/admin/dhlpoland/shipment/edit/entity_id/3');

        $this->response->expects($this->once())
            ->method('setRedirect')
            ->with('http://example.com/admin/dhlpoland/shipment/edit/entity_id/3');

        $subject = $this->createMock(NativeShipmentViewController::class);
        $proceed = fn () => $this->fail('Proceed should not be called when redirecting');

        $result = $this->plugin->aroundExecute($subject, $proceed);

        $this->assertNull($result);
    }

    public function testAroundExecuteAddsNoticeMessageWhenDhlShipmentWasDeleted(): void
    {
        $this->request->method('getParam')->with('shipment_id')->willReturn('28');

        $this->shipmentDataRepository->expects($this->once())
            ->method('getByMagentoShipmentId')
            ->with(28)
            ->willReturn(null);

        $shipmentMock = $this->createMock(ShipmentInterface::class);
        $shipmentMock->method('getOrderId')->willReturn(26);

        $this->shipmentRepository->expects($this->once())
            ->method('get')
            ->with(28)
            ->willReturn($shipmentMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getShippingMethod')->willReturn('dhlpoland_dhlpoland');

        $this->orderRepository->expects($this->once())
            ->method('get')
            ->with(26)
            ->willReturn($orderMock);

        $emptyMessageCollection = $this->createMock(MessageCollection::class);
        $emptyMessageCollection->method('getItems')->willReturn([]);
        $this->messageManager->method('getMessages')->willReturn($emptyMessageCollection);

        $this->messageManager->expects($this->once())
            ->method('addNoticeMessage');

        $subject = $this->createMock(NativeShipmentViewController::class);
        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return 'native_result';
        };

        $result = $this->plugin->aroundExecute($subject, $proceed);

        $this->assertTrue($proceedCalled);
        $this->assertEquals('native_result', $result);
    }

    public function testAroundExecuteDoesNotAddNoticeMessageWhenShipmentWasNeverDhl(): void
    {
        $this->request->method('getParam')->with('shipment_id')->willReturn('15');

        $this->shipmentDataRepository->expects($this->once())
            ->method('getByMagentoShipmentId')
            ->with(15)
            ->willReturn(null);

        $shipmentMock = $this->createMock(ShipmentInterface::class);
        $shipmentMock->method('getOrderId')->willReturn(10);

        $this->shipmentRepository->expects($this->once())
            ->method('get')
            ->with(15)
            ->willReturn($shipmentMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getShippingMethod')->willReturn('flatrate_flatrate');

        $this->orderRepository->expects($this->once())
            ->method('get')
            ->with(10)
            ->willReturn($orderMock);

        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $subject = $this->createMock(NativeShipmentViewController::class);
        $proceed = fn () => 'native_result';

        $result = $this->plugin->aroundExecute($subject, $proceed);

        $this->assertEquals('native_result', $result);
    }

    public function testAroundExecuteDoesNotDuplicateNoticeMessageWhenAlreadyQueued(): void
    {
        $this->request->method('getParam')->with('shipment_id')->willReturn('28');

        $this->shipmentDataRepository->expects($this->once())
            ->method('getByMagentoShipmentId')
            ->with(28)
            ->willReturn(null);

        $shipmentMock = $this->createMock(ShipmentInterface::class);
        $shipmentMock->method('getOrderId')->willReturn(26);

        $this->shipmentRepository->expects($this->once())
            ->method('get')
            ->with(28)
            ->willReturn($shipmentMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getShippingMethod')->willReturn('dhlpoland_dhlpoland');

        $this->orderRepository->expects($this->once())
            ->method('get')
            ->with(26)
            ->willReturn($orderMock);

        $existingMessage = $this->createMock(MessageInterface::class);
        $existingMessage->method('getText')->willReturn(
            'DHL shipment has been deleted. Redirected to the default Magento shipment view.'
        );

        $messageCollection = $this->createMock(MessageCollection::class);
        $messageCollection->method('getItems')->willReturn([$existingMessage]);
        $this->messageManager->method('getMessages')->willReturn($messageCollection);

        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $subject = $this->createMock(NativeShipmentViewController::class);
        $proceed = fn () => 'native_result';

        $result = $this->plugin->aroundExecute($subject, $proceed);

        $this->assertEquals('native_result', $result);
    }

    public function testAroundExecuteCatchesAndLogsExceptionGracefully(): void
    {
        $this->request->method('getParam')->with('shipment_id')->willReturn('99');

        $this->shipmentDataRepository->expects($this->once())
            ->method('getByMagentoShipmentId')
            ->with(99)
            ->willThrowException(new Exception('DB connection failed'));

        $this->logger->expects($this->once())
            ->method('error');

        $subject = $this->createMock(NativeShipmentViewController::class);
        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return 'native_result';
        };

        $result = $this->plugin->aroundExecute($subject, $proceed);

        $this->assertTrue($proceedCalled);
        $this->assertEquals('native_result', $result);
    }

    public function testAroundExecuteWorksWithShippingOrderShipmentViewController(): void
    {
        $this->request->method('getParam')->with('shipment_id')->willReturn('28');

        $this->shipmentDataRepository->expects($this->once())
            ->method('getByMagentoShipmentId')
            ->with(28)
            ->willReturn(null);

        $shipmentMock = $this->createMock(ShipmentInterface::class);
        $shipmentMock->method('getOrderId')->willReturn(26);

        $this->shipmentRepository->expects($this->once())
            ->method('get')
            ->with(28)
            ->willReturn($shipmentMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getShippingMethod')->willReturn('dhlpoland_dhlpoland');

        $this->orderRepository->expects($this->once())
            ->method('get')
            ->with(26)
            ->willReturn($orderMock);

        $emptyMessageCollection = $this->createMock(MessageCollection::class);
        $emptyMessageCollection->method('getItems')->willReturn([]);
        $this->messageManager->method('getMessages')->willReturn($emptyMessageCollection);

        $this->messageManager->expects($this->once())
            ->method('addNoticeMessage');

        $shippingViewController = $this->createMock(\Magento\Shipping\Controller\Adminhtml\Order\Shipment\View::class);
        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return 'shipping_view_result';
        };

        $result = $this->plugin->aroundExecute($shippingViewController, $proceed);

        $this->assertTrue($proceedCalled);
        $this->assertEquals('shipping_view_result', $result);
    }
}
