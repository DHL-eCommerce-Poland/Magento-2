<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Controller\Adminhtml\Shipment\CreateReturnShipment;
use Ideo\Dhl\Model\Api\CreateShipmentReturnResult;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ReturnShipmentDataBuilder;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreateReturnShipmentTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $contextMock;
    /**
     * @var MockObject
     */
    private $requestMock;
    /**
     * @var MockObject
     */
    private $messageManagerMock;
    /**
     * @var MockObject
     */
    private $redirectFactoryMock;
    /**
     * @var MockObject
     */
    private $redirectMock;
    /**
     * @var MockObject
     */
    private $shipmentDataServiceMock;
    /**
     * @var MockObject
     */
    private $returnBuilderMock;
    /**
     * @var MockObject
     */
    private $returnCreationServiceMock;
    /**
     * @var MockObject
     */
    private $scopeConfigMock;
    /**
     * @var MockObject
     */
    private $loggerMock;
    /**
     * @var CreateReturnShipment
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->redirectMock = $this->createMock(Redirect::class);
        $this->shipmentDataServiceMock = $this->createMock(ShipmentDataService::class);
        $this->returnBuilderMock = $this->createMock(ReturnShipmentDataBuilder::class);
        $this->returnCreationServiceMock = $this->createMock(ReturnShipmentCreationService::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->loggerMock = $this->createStub(LoggerInterface::class);

        $this->contextMock->expects($this->once())->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->expects($this->once())->method('getMessageManager')->willReturn($this->messageManagerMock);
        $this->contextMock->expects($this->once())
            ->method('getResultRedirectFactory')
            ->willReturn($this->redirectFactoryMock);

        $this->redirectFactoryMock->expects($this->once())->method('create')->willReturn($this->redirectMock);
        $this->redirectMock->expects($this->once())->method('setPath')->with('*/*/index')->willReturnSelf();

        $this->controller = new CreateReturnShipment(
            $this->contextMock,
            $this->shipmentDataServiceMock,
            $this->returnBuilderMock,
            $this->returnCreationServiceMock,
            $this->scopeConfigMock,
            $this->loggerMock
        );
    }

    public function testExecuteWithInvalidEntityIdRedirects(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn('0');

        $this->shipmentDataServiceMock->expects($this->never())
            ->method('getShipmentEntity');

        $this->returnBuilderMock->expects($this->never())
            ->method('buildReturnShipmentData');

        $this->returnCreationServiceMock->expects($this->never())
            ->method('createReturnShipment');

        $this->scopeConfigMock->expects($this->never())
            ->method('getValue');

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }

    public function testExecuteSuccessCreatesReturnShipment(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with('entity_id')
            ->willReturn('10');

        $entityMock = $this->createStub(ShipmentDataInterface::class);
        $entityMock->method('getShipmentId')->willReturn('WAYBILL123');

        $this->shipmentDataServiceMock->expects($this->once())
            ->method('getShipmentEntity')
            ->with(10)
            ->willReturn($entityMock);

        $shipmentData = new DhlShipmentData(orderId: 100);
        $this->shipmentDataServiceMock->expects($this->once())
            ->method('loadShipmentDataFromEntity')
            ->with($entityMock)
            ->willReturn($shipmentData);

        $returnShipmentData = new DhlShipmentData(orderId: 100);
        $this->returnBuilderMock->expects($this->once())
            ->method('buildReturnShipmentData')
            ->with($shipmentData)
            ->willReturn($returnShipmentData);

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('carriers/dhlparcel/sandbox')
            ->willReturn('1');

        $apiResult = new CreateShipmentReturnResult(
            success: true,
            timeout: false,
            items: [
                ['shipmentId' => 'RET999', 'waybill' => 'RET999'],
            ]
        );

        $this->returnCreationServiceMock->expects($this->once())
            ->method('createReturnShipment')
            ->with(
                returnShipmentData: $returnShipmentData,
                environment: 'sandbox',
                expirationDays: 14,
                primaryWaybillNumber: 'WAYBILL123',
                originalEntityId: 10
            )
            ->willReturn($apiResult);

        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->redirectMock, $result);
    }
}
