<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\Save;
use Ideo\Dhl\Model\Api\CreateShipmentsResult;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SaveTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private Context|MockObject $contextStub;

    /**
     * @var RequestInterface|MockObject
     */
    private RequestInterface|MockObject $requestStub;

    /**
     * @var ShipmentDataService|MockObject
     */
    private ShipmentDataService|MockObject $shipmentDataServiceMock;

    /**
     * @var ShipmentCreationService|MockObject
     */
    private ShipmentCreationService|MockObject $shipmentCreationServiceMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface|MockObject $scopeConfigMock;

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
     * @var Save
     */
    private Save $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->shipmentDataServiceMock = $this->createStub(ShipmentDataService::class);
        $this->shipmentCreationServiceMock = $this->createMock(ShipmentCreationService::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->jsonFactoryStub = $this->createStub(JsonFactory::class);
        $this->jsonResultMock = $this->createMock(Json::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $messageManagerStub = $this->createStub(\Magento\Framework\Message\ManagerInterface::class);
        $resultRedirectFactoryStub = $this->createStub(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $resultRedirectStub = $this->createStub(\Magento\Framework\Controller\Result\Redirect::class);
        $resultRedirectFactoryStub->method('create')->willReturn($resultRedirectStub);

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getMessageManager')->willReturn($messageManagerStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($resultRedirectFactoryStub);
        $this->jsonFactoryStub->method('create')->willReturn($this->jsonResultMock);

        $serializerStub = $this->createStub(\Magento\Framework\Serialize\SerializerInterface::class);
        $serializerStub->method('unserialize')->willReturnCallback(function ($data) {
            return is_string($data) ? json_decode($data, true) : null;
        });

        $this->controller = new Save(
            $this->contextStub,
            $this->shipmentDataServiceMock,
            $this->shipmentCreationServiceMock,
            $this->scopeConfigMock,
            $this->jsonFactoryStub,
            $serializerStub,
            $this->loggerStub
        );
    }

    /**
     * Test execute handles invalid/empty payload cleanly.
     *
     * @return void
     */
    public function testExecuteInvalidPayload(): void
    {
        $this->requestStub
            ->method('getParam')
            ->willReturnMap([
                ['draft', null, null],
                ['shipment_data', null, null],
                ['isAjax', null, true],
            ]);

        $this->scopeConfigMock->expects(self::never())->method('getValue');
        $this->shipmentCreationServiceMock->expects(self::never())->method('createShipment');

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(function (array $data) {
                return $data['success'] === false;
            }))
            ->willReturn($this->jsonResultMock);

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }

    /**
     * Test execute dispatches directly to DHL API successfully.
     *
     * @return void
     */
    public function testExecuteDirectDispatchSuccess(): void
    {
        $payloadData = [
            'orderId' => 123,
            'sender' => ['name' => 'Jan Kowalski'],
            'recipient' => ['name' => 'Firma ABC'],
            'specialServices' => [],
            'shipmentSettings' => ['serviceType' => 'AH']
        ];

        $this->requestStub
            ->method('getParam')
            ->willReturnCallback(function (string $param) use ($payloadData) {
                if ($param === 'draft') {
                    return json_encode($payloadData);
                }
                if ($param === 'preset_sender_id') {
                    return '1';
                }
                if ($param === 'preset_shipment_id') {
                    return '2';
                }
                return null;
            });

        $this->scopeConfigMock->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlparcel/sandbox')
            ->willReturn('1');

        $apiResult = new CreateShipmentsResult(true, false, '90039521201');
        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('createShipment')
            ->with(Assert::callback(fn ($arg) => $arg instanceof DhlShipmentData), 'sandbox', '1', '2')
            ->willReturn($apiResult);

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(function (array $data) {
                return $data['success'] === true
                    && $data['shipmentId'] === '90039521201'
                    && $data['status'] === 'created';
            }))
            ->willReturn($this->jsonResultMock);

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }

    /**
     * Test execute returns error if DHL API returns failure.
     *
     * @return void
     */
    public function testExecuteDispatchFailure(): void
    {
        $payloadData = [
            'orderId' => 123,
            'sender' => ['name' => 'Jan Kowalski'],
            'recipient' => ['name' => 'Firma ABC'],
            'specialServices' => [],
            'shipmentSettings' => ['serviceType' => 'AH']
        ];

        $this->requestStub
            ->method('getParam')
            ->willReturnCallback(function (string $param) use ($payloadData) {
                if ($param === 'draft') {
                    return json_encode($payloadData);
                }
                return null;
            });

        $this->scopeConfigMock->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlparcel/sandbox')
            ->willReturn('0');

        $apiResult = new CreateShipmentsResult(false, false, null, 'ERR_01', 'Nieprawidłowy kod pocztowy');
        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('createShipment')
            ->with(Assert::callback(fn ($arg) => $arg instanceof DhlShipmentData), 'production', null, null)
            ->willReturn($apiResult);

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(function (array $data) {
                return $data['success'] === false
                    && $data['message'] === 'Nieprawidłowy kod pocztowy';
            }))
            ->willReturn($this->jsonResultMock);

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }
}
