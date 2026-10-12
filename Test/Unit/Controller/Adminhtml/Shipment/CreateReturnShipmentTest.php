<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Exception;
use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Controller\Adminhtml\Shipment\CreateReturnShipment;
use DhlPoland\Dhl\Model\Api\CreateShipmentReturnResult;
use DhlPoland\Dhl\Model\Shipment\DhlShipmentData;
use DhlPoland\Dhl\Model\Shipment\ReturnShipmentCreationService;
use DhlPoland\Dhl\Model\Shipment\ReturnShipmentDataBuilder;
use DhlPoland\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreateReturnShipmentTest extends TestCase
{
    /**
     * @var Stub
     */
    private $contextStub;
    /**
     * @var Stub
     */
    private $requestStub;
    /**
     * @var Stub
     */
    private $messageManagerStub;
    /**
     * @var Stub
     */
    private $redirectFactoryStub;
    /**
     * @var Stub
     */
    private $redirectStub;
    /**
     * @var Stub
     */
    private $shipmentDataServiceStub;
    /**
     * @var Stub
     */
    private $returnBuilderStub;
    /**
     * @var Stub
     */
    private $returnCreationServiceStub;
    /**
     * @var Stub
     */
    private $loggerStub;
    /**
     * @var Stub
     */
    private $jsonFactoryStub;
    /**
     * @var CreateReturnShipment
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(HttpRequest::class);
        $this->messageManagerStub = $this->createStub(ManagerInterface::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->redirectStub->method('setPath')->willReturnSelf();
        $this->shipmentDataServiceStub = $this->createStub(ShipmentDataService::class);
        $this->returnBuilderStub = $this->createStub(ReturnShipmentDataBuilder::class);
        $this->returnCreationServiceStub = $this->createStub(ReturnShipmentCreationService::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->jsonFactoryStub = $this->createStub(JsonFactory::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getMessageManager')->willReturn($this->messageManagerStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($this->redirectFactoryStub);
        $this->redirectFactoryStub->method('create')->willReturn($this->redirectStub);

        $this->controller = new CreateReturnShipment(
            $this->contextStub,
            $this->shipmentDataServiceStub,
            $this->returnBuilderStub,
            $this->returnCreationServiceStub,
            $this->loggerStub,
            $this->jsonFactoryStub
        );
    }

    public function testExecuteWithInvalidEntityIdRedirectsToIndex(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(false);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 0 : null;
        });

        $calledPath = null;
        $this->redirectStub->method('setPath')->willReturnCallback(function (string $path) use (&$calledPath) {
            $calledPath = $path;
            return $this->redirectStub;
        });

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
        $this->assertEquals('*/*/index', $calledPath);
    }

    public function testExecuteAjaxWithInvalidEntityIdReturnsJson(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(true);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 0 : null;
        });

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())->method('setData')->with([
            'success' => false,
            'message' => (string) __('Invalid shipment entity ID.'),
        ])->willReturnSelf();
        $this->jsonFactoryStub->method('create')->willReturn($jsonMock);

        $result = $this->controller->execute();
        $this->assertSame($jsonMock, $result);
    }

    public function testExecuteSuccessRedirectsToEdit(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(false);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 10 : null;
        });

        $entityStub = $this->createStub(ShipmentDataInterface::class);
        $entityStub->method('getShipmentId')->willReturn('WAYBILL123');

        $this->shipmentDataServiceStub->method('getShipmentEntity')->willReturn($entityStub);

        $shipmentData = new DhlShipmentData(orderId: 100);
        $this->shipmentDataServiceStub->method('loadShipmentDataFromEntity')->willReturn($shipmentData);

        $returnShipmentData = new DhlShipmentData(orderId: 100);
        $this->returnBuilderStub->method('buildReturnShipmentData')->willReturn($returnShipmentData);

        $apiResult = new CreateShipmentReturnResult(
            success: true,
            timeout: false,
            items: [['shipmentId' => 'RET999', 'waybill' => 'RET999']]
        );

        $this->returnCreationServiceStub->method('createReturnShipment')->willReturn($apiResult);

        $calledPath = null;
        $calledParams = null;
        $this->redirectStub->method('setPath')->willReturnCallback(
            function (string $path, array $params = []) use (&$calledPath, &$calledParams) {
                $calledPath = $path;
                $calledParams = $params;
                return $this->redirectStub;
            }
        );

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
        $this->assertEquals('*/*/edit', $calledPath);
        $this->assertEquals(['entity_id' => 10], $calledParams);
    }

    public function testExecuteAjaxSuccessReturnsJson(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(true);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 10 : null;
        });

        $entityStub = $this->createStub(ShipmentDataInterface::class);
        $entityStub->method('getShipmentId')->willReturn('WAYBILL123');
        $this->shipmentDataServiceStub->method('getShipmentEntity')->willReturn($entityStub);

        $shipmentData = new DhlShipmentData(orderId: 100);
        $this->shipmentDataServiceStub->method('loadShipmentDataFromEntity')->willReturn($shipmentData);

        $returnShipmentData = new DhlShipmentData(orderId: 100);
        $this->returnBuilderStub->method('buildReturnShipmentData')->willReturn($returnShipmentData);

        $apiResult = new CreateShipmentReturnResult(
            success: true,
            timeout: false,
            items: [['shipmentId' => 'RET999', 'waybill' => 'RET999']]
        );
        $this->returnCreationServiceStub->method('createReturnShipment')->willReturn($apiResult);

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())->method('setData')->with($this->callback(function (array $data): bool {
            return $data['success'] === true && $data['shipmentId'] === 'RET999';
        }))->willReturnSelf();
        $this->jsonFactoryStub->method('create')->willReturn($jsonMock);

        $result = $this->controller->execute();
        $this->assertSame($jsonMock, $result);
    }

    public function testExecuteAjaxErrorReturnsJson(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(true);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 10 : null;
        });

        $this->shipmentDataServiceStub->method('getShipmentEntity')
            ->willThrowException(new Exception('Shipment not found'));

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())->method('setData')->with($this->callback(function (array $data): bool {
            return $data['success'] === false && str_contains($data['message'], 'Shipment not found');
        }))->willReturnSelf();
        $this->jsonFactoryStub->method('create')->willReturn($jsonMock);

        $result = $this->controller->execute();
        $this->assertSame($jsonMock, $result);
    }
}
