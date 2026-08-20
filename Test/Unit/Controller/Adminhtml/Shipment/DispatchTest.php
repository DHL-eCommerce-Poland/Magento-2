<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\Dispatch;
use Ideo\Dhl\Model\Api\CreateShipmentsResult;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DispatchTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private Context|MockObject $contextStub;

    /**
     * @var RequestInterface|MockObject
     */
    private RequestInterface|MockObject $requestMock;

    /**
     * @var ShipmentCreationService|MockObject
     */
    private ShipmentCreationService|MockObject $serviceStub;

    /**
     * @var JsonFactory|MockObject
     */
    private JsonFactory|MockObject $jsonFactoryStub;

    /**
     * @var Json|MockObject
     */
    private Json|MockObject $jsonResultMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $loggerStub;

    /**
     * @var Dispatch
     */
    private Dispatch $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->serviceStub = $this->createStub(ShipmentCreationService::class);
        $this->jsonFactoryStub = $this->createStub(JsonFactory::class);
        $this->jsonResultMock = $this->createMock(Json::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestMock);
        $this->jsonFactoryStub->method('create')->willReturn($this->jsonResultMock);

        $this->controller = new Dispatch(
            $this->contextStub,
            $this->serviceStub,
            $this->jsonFactoryStub,
            $this->loggerStub
        );
    }

    /**
     * Test execute method returns success JSON when API succeeds.
     *
     * @return void
     */
    public function testExecuteSuccess(): void
    {
        $this->requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->willReturnMap([
                ['entity_id', null, 123],
                ['environment', null, 'sandbox'],
            ]);

        $apiResult = new CreateShipmentsResult(true, false, '1234567890');
        $this->serviceStub->method('dispatchShipment')->willReturn($apiResult);

        $this->jsonResultMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === true && $data['shipmentId'] === '1234567890';
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }

    /**
     * Test execute method handles missing entity ID.
     *
     * @return void
     */
    public function testExecuteMissingEntityId(): void
    {
        $this->requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->willReturnMap([
                ['entity_id', null, null],
                ['environment', null, 'sandbox'],
            ]);

        $this->jsonResultMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return $data['success'] === false;
            }))
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }
}
