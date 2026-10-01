<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Plugin\Adminhtml\Order\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Plugin\Adminhtml\Order\Shipment\RedirectNativeViewPlugin;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Sales\Controller\Adminhtml\Shipment\View as NativeShipmentViewController;
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
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->plugin = new RedirectNativeViewPlugin(
            $this->request,
            $this->response,
            $this->urlBuilder,
            $this->shipmentDataRepository,
            $this->logger
        );
    }

    public function testAroundExecuteProceedsWhenNoShipmentIdInRequest(): void
    {
        $this->request->method('getParam')->with('shipment_id')->willReturn(null);
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
}
