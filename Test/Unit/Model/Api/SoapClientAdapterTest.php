<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\SoapClientAdapter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class SoapClientAdapterTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerStub;

    /**
     * @var SoapClientAdapter
     */
    private $adapter;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigStub;

    /**
     * @var TimezoneInterface|MockObject
     */
    private $timezoneMock;
    /**
     * @var \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer
     */
    private $dateSanitizer;

    /**
     * @var \Magento\Framework\Filesystem\Driver\File|\PHPUnit\Framework\MockObject\MockObject
     */
    private $fileDriverStub;

    /**
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->timezoneMock = $this->createMock(TimezoneInterface::class);
        $this->timezoneMock->method('scopeDate')
            ->willReturn(new \DateTime('2026-09-02'));

        $this->dateSanitizer = new \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer($this->timezoneMock);
        $this->fileDriverStub = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);

        $this->adapter = new SoapClientAdapter(
            $this->loggerStub,
            $this->scopeConfigStub,
            $this->dateSanitizer,
            $this->fileDriverStub
        );
    }

    /**
     * Test XML authentication credential masking logic.
     *
     * @return void
     */
    public function testSanitizeXmlMasksPasswordWhilePreservingUsername(): void
    {
        $rawXml = '<soap:Envelope><AuthData><username>user1</username>'
            . '<password>Secret123!</password></AuthData></soap:Envelope>';

        $sanitized = $this->adapter->sanitizeXml($rawXml);

        $this->assertStringContainsString('<username>user1</username>', $sanitized);
        $this->assertStringNotContainsString('Secret123!', $sanitized);
        $this->assertStringContainsString('<password>***MASKED***</password>', $sanitized);
    }

    public function testSanitizeXmlTruncatesLabelData(): void
    {
        $rawXml = '<soap:Envelope><item><shipmentId>12345</shipmentId>' .
            '<labelData>JVBERi0xLjUKJeLjz9MKNiAwIG9iago8PC9Db2xvclNwYWNlL0RldmljZUdyYXk=</labelData>' .
            '</item></soap:Envelope>';

        $sanitized = $this->adapter->sanitizeXml($rawXml);

        $this->assertStringNotContainsString(
            'JVBERi0xLjUKJeLjz9MKNiAwIG9iago8PC9Db2xvclNwYWNlL0RldmljZUdyYXk=',
            $sanitized
        );
        $this->assertStringContainsString('<labelData>***BINARY DATA TRUNCATED***</labelData>', $sanitized);
        $this->assertStringContainsString('<shipmentId>12345</shipmentId>', $sanitized);
    }

    public function testSanitizeXmlTruncatesScanData(): void
    {
        $rawXml = '<soap:Envelope><getShipmentScanResult>' .
            '<scanData>iVBORw0KGgoAAAANSUhEUgAAAXgAAAF4CAYAAABeneKmAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQU' .
            '</scanData><scanMimeType>image/png</scanMimeType></getShipmentScanResult></soap:Envelope>';

        $sanitized = $this->adapter->sanitizeXml($rawXml);

        $this->assertStringNotContainsString(
            'iVBORw0KGgoAAAANSUhEUgAAAXgAAAF4CAYAAABeneKmAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQU',
            $sanitized
        );
        $this->assertStringContainsString('<scanData>***BINARY DATA TRUNCATED***</scanData>', $sanitized);
        $this->assertStringContainsString('<scanMimeType>image/png</scanMimeType>', $sanitized);
    }

    public function testSanitizeXmlTruncatesInvoiceData(): void
    {
        $rawXml = '<soap:Envelope><customs><invoice>JVBERi0xLjUKJeLjz9MKNiAwIG9iago8PC9Db2xvclNwYWNlL0RldmljZUdyYXk=' .
            '</invoice></customs></soap:Envelope>';

        $sanitized = $this->adapter->sanitizeXml($rawXml);

        $this->assertStringNotContainsString(
            'JVBERi0xLjUKJeLjz9MKNiAwIG9iago8PC9Db2xvclNwYWNlL0RldmljZUdyYXk=',
            $sanitized
        );
        $this->assertStringContainsString('<invoice>***BINARY DATA TRUNCATED***</invoice>', $sanitized);
    }

    /**
     * Test UTF-8 sanitization for invalid non-UTF-8 byte sequences.
     *
     * @return void
     */
    public function testEnsureUtf8SanitizesInvalidByteSequences(): void
    {
        $invalidUtf8 = ",C\x9b\r\no \$6!E";
        $sanitized = $this->adapter->ensureUtf8($invalidUtf8);

        $this->assertTrue(mb_check_encoding($sanitized, 'UTF-8'));
    }

    /**
     * Test successful testConnection via mocked SoapClient.
     *
     * @return void
     */
    public function testTestConnectionSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->testConnection($credentials);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Connection', $result->getMessage());
    }

    /**
     * Test direct getInternationalParams2 call via mocked SoapClient.
     *
     * @return void
     */
    public function testGetInternationalParams2Success(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getInternationalParams2($credentials);

        $this->assertIsArray($result);
    }

    /**
     * Test testConnection with SoapFault failure.
     *
     * @return void
     */
    public function testTestConnectionSoapFault(): void
    {
        $soapClientStub = new TestSoapClient(true, 'Rejected user wrong_pass');

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('error')
            ->with('[DHL WebAPI2] SoapFault during testConnection: [AUTH_ERR] Rejected [redacted] [redacted]');

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $loggerMock,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'wrong_pass', false);
        $result = $adapter->testConnection($credentials);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(
            'DHL connection failed. Verify the API credentials and try again.',
            $result->getMessage()
        );
        $this->assertStringNotContainsString('Invalid credentials', $result->getMessage());
        $this->assertEquals('AUTH_ERR', $result->getErrorCode());
    }

    public function testTestConnectionUnexpectedFailureIsLoggedWithoutCredentials(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('error')
            ->with('[DHL WebAPI2] Exception during testConnection: Failed for [redacted] using [redacted]');

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $loggerMock,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willThrowException(new RuntimeException('Failed for secret_user using secret_pass'));

        $result = $adapter->testConnection(new Credentials('secret_user', 'secret_pass', true));

        $this->assertFalse($result->isSuccess());
        $this->assertSame(
            'DHL connection failed. Verify the API credentials and try again.',
            $result->getMessage()
        );
        $this->assertStringNotContainsString('secret_user', $result->getMessage());
        $this->assertStringNotContainsString('secret_pass', $result->getMessage());
    }

    /**
     * Test successful createShipment via mocked SoapClient.
     *
     * @return void
     */
    public function testCreateShipmentSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->createShipment($credentials, ['content' => 'Test']);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isTimeout());
        $this->assertEquals('1234567890', $result->getShipmentId());
        $this->assertEmpty($result->getPackageNumbers());
    }

    /**
     * Test that createShipment SOAP XML retains decimal package measurements and nullable fields.
     *
     * @return void
     */
    public function testCreateShipmentSoapRequestKeepsFractionalMeasurementsAndNilValues(): void
    {
        $wsdl = __DIR__ . '/_files/create-shipment-measurements.wsdl';
        $soapClient = new class($wsdl) extends \SoapClient {
            /** @var string */
            public string $capturedRequest = '';

            public function __construct(string $wsdlPath)
            {
                parent::__construct($wsdlPath, [
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'exceptions' => true,
                    'trace' => true,
                ]);
            }

            public function __doRequest(
                string $request,
                string $location,
                string $action,
                int $version,
                bool $oneWay = false,
                ?string $uriParserClass = null
            ): ?string {
                $this->capturedRequest = $request;

                return '<?xml version="1.0"?>'
                    . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
                    . '<SOAP-ENV:Body><createShipmentResponse xmlns="urn:dhlpoland:dhl:test">'
                    . '<createShipmentResult>OK</createShipmentResult>'
                    . '</createShipmentResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>';
            }
        };

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClient);

        $adapter->createShipment(new Credentials('user', 'pass', true), [
            'shipmentInfo' => [],
            'pieceList' => [
                'item' => [
                    [
                        'type' => 'PACKAGE',
                        'weight' => '0.12',
                        'width' => '0.5',
                        'height' => '0.5',
                        'length' => '0.5',
                        'quantity' => 1,
                    ],
                    [
                        'type' => 'PACKAGE',
                        'weight' => null,
                        'width' => null,
                        'height' => null,
                        'length' => null,
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('<weight>0.12</weight>', $soapClient->capturedRequest);
        $this->assertStringContainsString('<width>0.5</width>', $soapClient->capturedRequest);
        $this->assertStringContainsString('<height>0.5</height>', $soapClient->capturedRequest);
        $this->assertStringContainsString('<length>0.5</length>', $soapClient->capturedRequest);
        $this->assertSame(4, substr_count($soapClient->capturedRequest, 'xsi:nil="true"'));
    }

    /**
     * Test createShipment handling timeout exception.
     *
     * @return void
     */
    public function testCreateShipmentTimeout(): void
    {
        $soapClientStub = new TestSoapClient(true);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->createShipment($credentials, ['content' => 'Test']);

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getShipmentId());
        $this->assertEquals('CREATE_ERR', $result->getErrorCode());
    }

    /**
     * Test successful deleteShipment via mocked SoapClient.
     *
     * @return void
     */
    public function testDeleteShipmentSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->deleteShipment($credentials, '1234567890');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('1234567890', $result->getShipmentId());
    }

    /**
     * Test deleteShipment failure on SoapFault.
     *
     * @return void
     */
    public function testDeleteShipmentFault(): void
    {
        $soapClientStub = new TestSoapClient(true);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->deleteShipment($credentials, '1234567890');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('DELETE_ERR', $result->getErrorCode());
    }

    /**
     * Test deleteShipment with return shipment IDs passes multiple items to SOAP shipments.
     *
     * @return void
     */
    public function testDeleteShipmentWithReturnShipmentIdsSuccess(): void
    {
        $soapClientMock = $this->createPartialMock(TestSoapClient::class, ['deleteShipments']);
        $soapClientMock->expects($this->once())
            ->method('deleteShipments')
            ->with([
                'authData' => [
                    'username' => 'user',
                    'password' => 'pass',
                ],
                'shipments' => [
                    'item' => ['1234567890', '90040146098', '90040146099'],
                ],
            ])
            ->willReturn((object) [
                'deleteShipmentsResult' => (object) [
                    'item' => [
                        (object) ['shipmentIdentification' => '1234567890', 'result' => true],
                        (object) ['shipmentIdentification' => '90040146098', 'result' => true],
                        (object) ['shipmentIdentification' => '90040146099', 'result' => true],
                    ],
                ],
            ]);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientMock);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->deleteShipment($credentials, '1234567890', ['90040146098', '90040146099']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('1234567890', $result->getShipmentId());
    }

    /**
     * Test deleteShipment with return shipment IDs reports error when one item fails.
     *
     * @return void
     */
    public function testDeleteShipmentWithReturnShipmentIdsPartialFailure(): void
    {
        $soapClientMock = $this->createPartialMock(TestSoapClient::class, ['deleteShipments']);
        $soapClientMock->expects($this->once())
            ->method('deleteShipments')
            ->willReturn((object) [
                'deleteShipmentsResult' => (object) [
                    'item' => [
                        (object) ['shipmentIdentification' => '1234567890', 'result' => true],
                        (object) [
                            'shipmentIdentification' => '90040146098',
                            'result' => false,
                            'error' => 'Przesylka nie istnieje',
                        ],
                    ],
                ],
            ]);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientMock);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->deleteShipment($credentials, '1234567890', ['90040146098']);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('DELETE_SHIPMENT_ERROR', $result->getErrorCode());
        $this->assertStringContainsString('[90040146098] Przesylka nie istnieje', (string) $result->getErrorMessage());
    }

    /**
     * Test successful bookCourier via mocked SoapClient.
     *
     * @return void
     */
    public function testBookCourierSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->bookCourier($credentials, ['1234567890'], '2026-08-28', '10:00', '14:00');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['ORDER_98765'], $result->getCourierOrderIds());
    }

    /**
     * Test that all courier order IDs are preserved from a multi-shipment response.
     *
     * @return void
     */
    public function testBookCourierPreservesEveryReturnedCourierOrderId(): void
    {
        $soapClientStub = new class extends TestSoapClient {
            /**
             * Return one courier order ID for each requested DHL shipment.
             *
             * @param mixed $args
             *
             * @return mixed
             */
            public function bookCourier(mixed $args): mixed
            {
                return (object) [
                    'bookCourierResult' => (object) [
                        'item' => ['90002561026WWW', '90002571026WWW'],
                    ],
                ];
            }
        };

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $result = $adapter->bookCourier(
            new Credentials('user', 'pass', true),
            ['DHL_SHIPMENT_1', 'DHL_SHIPMENT_2'],
            '2026-10-06',
            '10:00',
            '14:00'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['90002561026WWW', '90002571026WWW'], $result->getCourierOrderIds());
    }

    /**
     * Test that the API adapter leaves order-to-service group mapping to the application service.
     *
     * @return void
     */
    public function testBookCourierAcceptsFewerOrderIdsThanRequestedShipments(): void
    {
        $soapClientStub = new class extends TestSoapClient {
            /**
             * Return an incomplete courier order ID list.
             *
             * @param mixed $args
             *
             * @return mixed
             */
            public function bookCourier(mixed $args): mixed
            {
                return (object) [
                    'bookCourierResult' => (object) [
                        'item' => ['90002561026WWW'],
                    ],
                ];
            }
        };

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $result = $adapter->bookCourier(
            new Credentials('user', 'pass', true),
            ['DHL_SHIPMENT_1', 'DHL_SHIPMENT_2'],
            '2026-10-06',
            '10:00',
            '14:00'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['90002561026WWW'], $result->getCourierOrderIds());
    }

    /**
     * Test SOAP request and response logging when DHL rejects a courier booking.
     *
     * @return void
     */
    public function testBookCourierLogsSanitizedSoapCommunicationOnFault(): void
    {
        $soapClientStub = new class extends TestSoapClient {
            /**
             * Throw a representative validation fault from DHL.
             *
             * @param mixed $args
             *
             * @return mixed
             * @throws \SoapFault
             */
            public function bookCourier(mixed $args): mixed
            {
                throw new \SoapFault(
                    '111',
                    'Booking multiple shipments requires the same pickup location.'
                );
            }

            /**
             * Return the SOAP request sent before the fault.
             *
             * @return string
             */
            public function __getLastRequest(): string
            {
                return '<Envelope><authData><username>private-user</username>'
                    . '<password>private-password</password></authData>'
                    . '<shipmentIdList><item>SHIPMENT_1</item><item>SHIPMENT_2</item></shipmentIdList></Envelope>';
            }

            /**
             * Return the SOAP fault response from DHL.
             *
             * @return string
             */
            public function __getLastResponse(): string
            {
                return '<Envelope><Fault><faultcode>111</faultcode>'
                    . '<faultstring>Invalid pickup location</faultstring></Fault></Envelope>';
            }
        };

        $loggedMessages = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(static function ($message) use (&$loggedMessages): void {
                $loggedMessages[] = (string) $message;
            });
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Booking multiple shipments'));

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $logger,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $result = $adapter->bookCourier(
            new Credentials('private-user', 'private-password', true),
            ['SHIPMENT_1', 'SHIPMENT_2'],
            '2026-10-06',
            '10:00',
            '14:00'
        );

        $logOutput = implode("\n", $loggedMessages);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('[DHL SOAP Request - bookCourier]', $logOutput);
        $this->assertStringContainsString('[DHL SOAP Response - bookCourier]', $logOutput);
        $this->assertStringContainsString('SHIPMENT_1', $logOutput);
        $this->assertStringContainsString('SHIPMENT_2', $logOutput);
        $this->assertStringContainsString('private-user', $logOutput);
        $this->assertStringNotContainsString('private-password', $logOutput);
        $this->assertStringContainsString('<username>private-user</username>', $logOutput);
        $this->assertStringContainsString('<password>***MASKED***</password>', $logOutput);
    }

    /**
     * Test successful cancelCourierBooking via mocked SoapClient.
     *
     * @return void
     */
    public function testCancelCourierBookingSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->cancelCourierBooking($credentials, 'ORDER_98765');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ORDER_98765', $result->getDispatchNumber());
    }

    /**
     * Test SOAP request and response logging on cancellation faults without exposing credentials.
     *
     * @return void
     */
    public function testCancelCourierBookingLogsSanitizedSoapCommunicationOnFault(): void
    {
        $soapClientStub = new class extends TestSoapClient {
            /**
             * Throw a SOAP fault to exercise cancellation error logging.
             *
             * @param mixed $args
             *
             * @return mixed
             * @throws \SoapFault
             */
            public function cancelCourierBooking(mixed $args): mixed
            {
                throw new \SoapFault('CANCEL_ERR', 'Remote cancellation fault');
            }

            /**
             * Return a representative SOAP request containing authentication values.
             *
             * @return string
             */
            public function __getLastRequest(): string
            {
                return '<Envelope><authData><username>private-user</username>'
                    . '<password>private-password</password></authData>'
                    . '<orders><item>ORDER_1</item></orders></Envelope>';
            }

            /**
             * Return a representative SOAP fault response.
             *
             * @return string
             */
            public function __getLastResponse(): string
            {
                return '<Envelope><Fault><faultstring>Remote cancellation fault</faultstring></Fault></Envelope>';
            }
        };

        $loggedMessages = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(static function ($message) use (&$loggedMessages): void {
                $loggedMessages[] = (string) $message;
            });
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Remote cancellation fault'));

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $logger,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $result = $adapter->cancelCourierBooking(
            new Credentials('private-user', 'private-password', true),
            'ORDER_1'
        );

        $logOutput = implode("\n", $loggedMessages);
        $this->assertFalse($result->isSuccess());
        $this->assertSame('DHL API error: Remote cancellation fault', $result->getErrorMessage());
        $this->assertStringContainsString('[DHL SOAP Request - cancelCourierBooking]', $logOutput);
        $this->assertStringContainsString('[DHL SOAP Response - cancelCourierBooking]', $logOutput);
        $this->assertStringContainsString('ORDER_1', $logOutput);
        $this->assertStringContainsString('private-user', $logOutput);
        $this->assertStringNotContainsString('private-password', $logOutput);
        $this->assertStringContainsString('<username>private-user</username>', $logOutput);
        $this->assertStringContainsString('<password>***MASKED***</password>', $logOutput);
    }

    /**
     * Test SOAP request and response logging on unexpected cancellation exceptions.
     *
     * @return void
     */
    public function testCancelCourierBookingLogsSoapCommunicationOnUnexpectedException(): void
    {
        $soapClientStub = new class extends TestSoapClient {
            /**
             * Throw an unexpected exception to exercise generic cancellation error logging.
             *
             * @param mixed $args
             *
             * @return mixed
             */
            public function cancelCourierBooking(mixed $args): mixed
            {
                throw new RuntimeException('Unexpected cancellation failure');
            }

            /**
             * Return a representative SOAP request.
             *
             * @return string
             */
            public function __getLastRequest(): string
            {
                return '<Envelope><orders><item>ORDER_1</item></orders></Envelope>';
            }

            /**
             * Return a representative SOAP fault response.
             *
             * @return string
             */
            public function __getLastResponse(): string
            {
                return '<Envelope><Fault><faultstring>Unexpected cancellation failure</faultstring></Fault></Envelope>';
            }
        };

        $loggedMessages = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(static function ($message) use (&$loggedMessages): void {
                $loggedMessages[] = (string) $message;
            });
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Unexpected cancellation failure'));

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $logger,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $result = $adapter->cancelCourierBooking(new Credentials('user', 'pass', true), 'ORDER_1');

        $logOutput = implode("\n", $loggedMessages);
        $this->assertFalse($result->isSuccess());
        $this->assertSame('CONNECTION_ERROR', $result->getErrorCode());
        $this->assertStringContainsString('[DHL SOAP Request - cancelCourierBooking]', $logOutput);
        $this->assertStringContainsString('[DHL SOAP Response - cancelCourierBooking]', $logOutput);
    }

    /**
     * Test cancellation failure handling when the SOAP client could not be created.
     *
     * @return void
     */
    public function testCancelCourierBookingHandlesFailureBeforeSoapClientCreation(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $logger->expects($this->once())->method('error');

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $logger,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();
        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willThrowException(new RuntimeException('Could not create SOAP client'));

        $result = $adapter->cancelCourierBooking(new Credentials('user', 'pass', true), 'ORDER_1');

        $this->assertFalse($result->isSuccess());
        $this->assertSame('CONNECTION_ERROR', $result->getErrorCode());
    }

    /**
     * Test successful getTrackAndTraceInfo via mocked SoapClient.
     *
     * @return void
     */
    public function testGetTrackAndTraceInfoSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getTrackAndTraceInfo($credentials, '1234567890');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('1234567890', $result->getShipmentId());
        $this->assertEquals('Jan Kowalski', $result->getReceivedBy());
        $this->assertEquals('Doręczono', $result->getLatestStatusDescription());
        $this->assertEquals('DOR', $result->getLatestStatusCode());
    }

    /**
     * Test getTrackAndTraceInfo with SoapFault failure.
     *
     * @return void
     */
    public function testGetTrackAndTraceInfoFault(): void
    {
        $soapClientStub = new TestSoapClient(true);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getTrackAndTraceInfo($credentials, '1234567890');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('TRACK_ERR', $result->getErrorCode());
        $this->assertStringContainsString('Error fetching tracking info', (string) $result->getErrorMessage());
    }

    /**
     * Test getPnp success.
     *
     * @return void
     */
    public function testGetPnpSuccess(): void
    {
        $soapClientStub = new TestSoapClient();

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getPnp($credentials, '2026-09-01', 'ALL');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('dummy_pnp_pdf_content', $result->getFileData());
        $this->assertEquals('PNP_2026-09-01.pdf', $result->getFileName());
        $this->assertEquals('application/pdf', $result->getMimeType());
    }

    /**
     * Test getPnp with SoapFault failure.
     *
     * @return void
     */
    public function testGetPnpFault(): void
    {
        $soapClientStub = new TestSoapClient(true);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub,
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getPnp($credentials, '2026-09-01', 'ALL');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('PNP_ERR', $result->getErrorCode());
        $this->assertStringContainsString('Error fetching PNP report', (string) $result->getErrorMessage());
    }

    /**
     * Test API origin constant is Luma_M2 and XML_PATH_API_ORIGIN config constant is removed.
     *
     * @return void
     */
    public function testApiOriginConstantIsLumaM2(): void
    {
        $reflection = new \ReflectionClass(SoapClientAdapter::class);
        $this->assertTrue($reflection->hasConstant('API_ORIGIN'));
        $this->assertEquals('Luma_M2', $reflection->getConstant('API_ORIGIN'));
        $this->assertFalse($reflection->hasConstant('XML_PATH_API_ORIGIN'));
    }
}
