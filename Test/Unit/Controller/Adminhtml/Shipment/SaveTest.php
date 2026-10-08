<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\Save;
use Ideo\Dhl\Model\Api\CreateShipmentResult;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class SaveTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private $contextStub;

    /**
     * @var Http|MockObject
     */
    private $requestStub;

    /**
     * @var ShipmentDataService|MockObject
     */
    private $shipmentDataServiceMock;

    /**
     * @var ShipmentCreationService|MockObject
     */
    private $shipmentCreationServiceMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var JsonFactory|MockObject
     */
    private $jsonFactoryStub;

    /**
     * @var Json|MockObject
     */
    private $jsonResultMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerStub;

    /**
     * @var Save
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(Http::class);
        $this->requestStub->method('isXmlHttpRequest')->willReturn(true);
        $this->shipmentDataServiceMock = $this->createStub(ShipmentDataService::class);
        $this->shipmentCreationServiceMock = $this->createMock(ShipmentCreationService::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->jsonFactoryStub = $this->createStub(JsonFactory::class);
        $this->jsonResultMock = $this->createMock(Json::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $messageManagerStub = $this->createStub(\Magento\Framework\Message\ManagerInterface::class);
        $resultRedirectFactoryStub = $this->createStub(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $resultRedirectStub = $this->createStub(\Magento\Framework\Controller\Result\Redirect::class);
        $resultRedirectStub->method('setPath')->willReturnSelf();
        $resultRedirectFactoryStub->method('create')->willReturn($resultRedirectStub);

        $backendHelperStub = $this->createStub(\Magento\Backend\Helper\Data::class);
        $backendHelperStub->method('getUrl')->willReturnCallback(function ($route, $params = []) {
            $entityId = $params['entity_id'] ?? '';
            return "https://example.com/admin/dhlpoland/shipment/edit/entity_id/{$entityId}";
        });

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getMessageManager')->willReturn($messageManagerStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($resultRedirectFactoryStub);
        $this->contextStub->method('getHelper')->willReturn($backendHelperStub);
        $this->jsonFactoryStub->method('create')->willReturn($this->jsonResultMock);

        $serializerStub = $this->createStub(\Magento\Framework\Serialize\SerializerInterface::class);
        $serializerStub->method('unserialize')->willReturnCallback(function ($data) {
            return is_string($data) ? json_decode($data, true) : null;
        });

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));
        $timezoneStub->method('date')->willReturnCallback(fn ($d) => new \DateTime((string)$d));

        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);
        $shipmentDataValidator = new \Ideo\Dhl\Model\Shipment\ShipmentDataValidator($timezoneStub);

        $this->controller = new Save(
            $this->contextStub,
            $this->shipmentCreationServiceMock,
            $this->scopeConfigMock,
            $this->jsonFactoryStub,
            $serializerStub,
            $timezoneStub,
            $dateSanitizer,
            $shipmentDataValidator,
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
                ['draft', null, '{"orderId":123}'],
                ['payload', null, '{"orderId":123}'],
                ['shipment_data', null, null],
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
            'shipmentSettings' => [
                'serviceType' => 'AH',
                'labelType' => 'LBLP',
                'returnLabelType' => 'ZBLP300',
                'returnQrCodeType' => 'QR6_IMG',
            ]
        ];

        $this->requestStub
            ->method('getParam')
            ->willReturnCallback(function (string $param) use ($payloadData) {
                if ($param === 'shipment_data') {
                    return json_encode($payloadData);
                }
                if ($param === 'drop_off_point_template_id') {
                    return '1';
                }
                if ($param === 'delivery_service_template_id') {
                    return '2';
                }
                return null;
            });

        $this->scopeConfigMock->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlpoland/sandbox')
            ->willReturn('1');

        $apiResult = (new CreateShipmentResult(true, false, '90039521201'))->withEntityId(101);
        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('createShipment')
            ->with(Assert::callback(fn ($arg) => $arg instanceof DhlShipmentData
                && $arg->getShipmentSettings()->getLabelType() === 'LBLP'
                && $arg->getShipmentSettings()->getReturnLabelType() === 'ZBLP300'
                && $arg->getShipmentSettings()->getReturnQrCodeType() === 'QR6_IMG'), 'sandbox', '1', '2', null)
            ->willReturn($apiResult);

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(function (array $data) {
                return $data['success'] === true
                    && $data['shipmentId'] === '90039521201'
                    && $data['entityId'] === 101
                    && $data['redirectUrl'] === 'https://example.com/admin/dhlpoland/shipment/edit/entity_id/101'
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
                if ($param === 'shipment_data') {
                    return json_encode($payloadData);
                }
                return null;
            });

        $this->scopeConfigMock->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlpoland/sandbox')
            ->willReturn('0');

        $apiResult = new CreateShipmentResult(false, false, null, null, [], 'ERR_01', 'Nieprawidłowy kod pocztowy');
        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('createShipment')
            ->with(Assert::callback(fn ($arg) => $arg instanceof DhlShipmentData), 'production', null, null, null)
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

    /**
     * Test execute accepts the active Magento UI Component data envelope.
     *
     * @return void
     */
    public function testExecuteAcceptsMagentoUiComponentDataEnvelope(): void
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
                if ($param === 'data') {
                    return $payloadData;
                }
                return null;
            });

        $this->scopeConfigMock->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlpoland/sandbox')
            ->willReturn('1');

        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('createShipment')
            ->with(Assert::callback(fn ($arg) => $arg instanceof DhlShipmentData), 'sandbox', null, null, null)
            ->willReturn(new CreateShipmentResult(true, false, '90039521201'));

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(fn (array $data) => $data['success'] === true))
            ->willReturn($this->jsonResultMock);

        $this->assertSame($this->jsonResultMock, $this->controller->execute());
    }

    /**
     * Test execute returns error when payload contains comma in numeric fields.
     *
     * @return void
     */
    public function testExecuteReturnsErrorWhenNumericFieldContainsComma(): void
    {
        $timezoneMock = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $validator = new \Ideo\Dhl\Model\Shipment\ShipmentDataValidator($timezoneMock);

        $serializerStub = $this->createStub(\Magento\Framework\Serialize\SerializerInterface::class);
        $serializerStub->method('unserialize')->willReturnCallback(function ($data) {
            return is_string($data) ? json_decode($data, true) : null;
        });

        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneMock);

        $controllerWithValidator = new Save(
            $this->contextStub,
            $this->shipmentCreationServiceMock,
            $this->scopeConfigMock,
            $this->jsonFactoryStub,
            $serializerStub,
            $timezoneMock,
            $dateSanitizer,
            $validator,
            $this->loggerStub
        );

        $payloadData = [
            'orderId' => 123,
            'sender' => ['name' => 'Jan Kowalski'],
            'recipient' => ['name' => 'Firma ABC'],
            'specialServices' => [],
            'shipmentSettings' => [
                'serviceType' => 'AH',
                'packages' => [
                    ['weight' => '12,5']
                ]
            ]
        ];

        $this->requestStub
            ->method('getParam')
            ->willReturnCallback(function (string $param) use ($payloadData) {
                if ($param === 'shipment_data') {
                    return json_encode($payloadData);
                }
                return null;
            });

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(function (array $data) {
                return $data['success'] === false
                    && str_contains($data['message'], 'Package #1 weight')
                    && str_contains($data['message'], 'cannot contain a comma');
            }))
            ->willReturn($this->jsonResultMock);

        $result = $controllerWithValidator->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }

    /**
     * Test execute calls recreateShipment when entity_id is provided and preserves packages from packages_fieldset.
     *
     * @return void
     */
    public function testExecuteRecreateShipmentWithPackagesFieldset(): void
    {
        $payloadData = [
            'entity_id' => 42,
            'orderId' => 123,
            'sender' => ['name' => 'Jan Kowalski'],
            'recipient' => ['name' => 'Firma ABC'],
            'specialServices' => [],
            'shipmentSettings' => [
                'serviceType' => 'AH',
                'labelType' => 'ZBLP300',
                'returnLabelType' => 'LBLP',
                'returnQrCodeType' => 'QR4_IMG',
                'packages' => [
                    ['weight' => 10.0, 'quantity' => 1],
                ],
            ],
            'packages_fieldset' => [
                'packages' => [
                    ['weight' => 10.0, 'quantity' => 1],
                    ['weight' => 5.0, 'quantity' => 1],
                ],
            ],
        ];

        $this->requestStub
            ->method('getParam')
            ->willReturnCallback(function (string $param) use ($payloadData) {
                if ($param === 'shipment_data') {
                    return json_encode($payloadData);
                }
                if ($param === 'entity_id') {
                    return 42;
                }
                return null;
            });

        $this->scopeConfigMock->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlpoland/sandbox')
            ->willReturn('1');

        $apiResult = new CreateShipmentResult(true, false, '90039521202');
        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('recreateShipment')
            ->with(
                42,
                Assert::callback(function ($arg) {
                    return $arg instanceof DhlShipmentData
                        && $arg->getShipmentSettings()->getLabelType() === 'ZBLP300'
                        && $arg->getShipmentSettings()->getReturnLabelType() === 'LBLP'
                        && $arg->getShipmentSettings()->getReturnQrCodeType() === 'QR4_IMG'
                        && count($arg->getShipmentSettings()->getPackages()) === 2
                        && $arg->getShipmentSettings()->getPackageQuantity() === 2;
                }),
                'sandbox'
            )
            ->willReturn($apiResult);

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(function (array $data) {
                return $data['success'] === true
                    && $data['shipmentId'] === '90039521202';
            }))
            ->willReturn($this->jsonResultMock);

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }

    public function testExecuteSanitizesCustomsInvoiceDate(): void
    {
        $payloadData = [
            'entity_id' => 42,
            'shipmentSettings' => [
                'shipmentDate' => '2026-09-10',
                'serviceType' => 'AH',
            ],
            'customs' => [
                'invoiceDate' => '10.08.2025',
                'customsType' => 'C',
            ],
        ];

        $this->requestStub
            ->method('getParam')
            ->willReturnCallback(function (string $param) use ($payloadData) {
                if ($param === 'shipment_data') {
                    return json_encode($payloadData);
                }
                if ($param === 'entity_id') {
                    return 42;
                }
                return null;
            });

        $this->scopeConfigMock
            ->method('getValue')
            ->with('carriers/dhlpoland/sandbox')
            ->willReturn('1');

        $apiResult = new CreateShipmentResult(true, false, '90039521202');
        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('recreateShipment')
            ->with(
                42,
                Assert::callback(function ($arg) {
                    return $arg instanceof DhlShipmentData
                        && $arg->getCustoms() !== null
                        && $arg->getCustoms()->getInvoiceDate() === '2025-08-10';
                }),
                'sandbox'
            )
            ->willReturn($apiResult);

        $this->jsonResultMock->expects(self::once())
            ->method('setData')
            ->with(Assert::callback(function (array $data) {
                return $data['success'] === true;
            }))
            ->willReturn($this->jsonResultMock);

        $result = $this->controller->execute();
        $this->assertSame($this->jsonResultMock, $result);
    }

    /**
     * Test execute non-AJAX redirects to shipment edit page on success.
     *
     * @return void
     */
    public function testExecuteNonAjaxSuccessRedirectsToEdit(): void
    {
        $nonAjaxRequest = $this->createStub(Http::class);
        $nonAjaxRequest->method('isXmlHttpRequest')->willReturn(false);

        $payloadData = [
            'orderId' => 123,
            'sender' => ['name' => 'Jan Kowalski'],
            'recipient' => ['name' => 'Firma ABC'],
            'specialServices' => [],
            'shipmentSettings' => ['serviceType' => 'AH'],
        ];

        $nonAjaxRequest->method('getParam')->willReturnCallback(function (string $param) use ($payloadData) {
            if ($param === 'shipment_data') {
                return json_encode($payloadData);
            }
            return null;
        });

        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectMock->expects(self::once())
            ->method('setPath')
            ->with('*/*/edit', ['entity_id' => 77])
            ->willReturnSelf();

        $resultRedirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $resultRedirectFactoryMock->expects(self::once())
            ->method('create')
            ->willReturn($redirectMock);

        $contextStub = $this->createStub(Context::class);
        $contextStub->method('getRequest')->willReturn($nonAjaxRequest);
        $contextStub->method('getMessageManager')
            ->willReturn($this->createStub(\Magento\Framework\Message\ManagerInterface::class));
        $contextStub->method('getResultRedirectFactory')->willReturn($resultRedirectFactoryMock);
        $contextStub->method('getHelper')->willReturn($this->createStub(\Magento\Backend\Helper\Data::class));

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));
        $timezoneStub->method('date')->willReturnCallback(fn ($d) => new \DateTime((string)$d));
        $dateSanitizer = new \Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);
        $shipmentDataValidator = new \Ideo\Dhl\Model\Shipment\ShipmentDataValidator($timezoneStub);
        $serializerStub = $this->createStub(\Magento\Framework\Serialize\SerializerInterface::class);
        $serializerStub->method('unserialize')
            ->willReturnCallback(fn ($d) => is_string($d) ? json_decode($d, true) : null);

        $controller = new Save(
            $contextStub,
            $this->shipmentCreationServiceMock,
            $this->scopeConfigMock,
            $this->jsonFactoryStub,
            $serializerStub,
            $timezoneStub,
            $dateSanitizer,
            $shipmentDataValidator,
            $this->loggerStub
        );

        $this->scopeConfigMock->expects(self::once())
            ->method('getValue')
            ->with('carriers/dhlpoland/sandbox')
            ->willReturn('1');

        $apiResult = (new CreateShipmentResult(true, false, '90039521201'))->withEntityId(77);
        $this->shipmentCreationServiceMock->expects(self::once())
            ->method('createShipment')
            ->willReturn($apiResult);

        $result = $controller->execute();
        $this->assertSame($redirectMock, $result);
    }
}
