<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\SoapClientAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SoapClientAdapterTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface|MockObject $loggerStub;

    /**
     * @var SoapClientAdapter
     */
    private SoapClientAdapter $adapter;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|MockObject
     */
    private \Magento\Framework\App\Config\ScopeConfigInterface|MockObject $scopeConfigStub;

    /**
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(\Magento\Framework\App\Config\ScopeConfigInterface::class);

        $this->adapter = new SoapClientAdapter(
            $this->loggerStub,
            $this->scopeConfigStub
        );
    }

    /**
     * Test XML password masking logic.
     *
     * @return void
     */
    public function testSanitizeXmlMasksPassword(): void
    {
        $rawXml = '<soap:Envelope><AuthData><username>user1</username>'
            . '<password>Secret123!</password></AuthData></soap:Envelope>';

        $sanitized = $this->adapter->sanitizeXml($rawXml);

        $this->assertStringNotContainsString('Secret123!', $sanitized);
        $this->assertStringNotContainsString('user1', $sanitized);
        $this->assertStringContainsString('<username>***MASKED***</username>', $sanitized);
        $this->assertStringContainsString('<password>***MASKED***</password>', $sanitized);
    }

    public function testSanitizeXmlTruncatesLabelData(): void
    {
        $rawXml = '<soap:Envelope><item><shipmentId>12345</shipmentId><labelData>JVBERi0xLjUKJeLjz9MKNiAwIG9iago8PC9Db2xvclNwYWNlL0RldmljZUdyYXk=</labelData></item></soap:Envelope>';

        $sanitized = $this->adapter->sanitizeXml($rawXml);

        $this->assertStringNotContainsString('JVBERi0xLjUKJeLjz9MKNiAwIG9iago8PC9Db2xvclNwYWNlL0RldmljZUdyYXk=', $sanitized);
        $this->assertStringContainsString('<labelData>***BINARY DATA TRUNCATED***</labelData>', $sanitized);
        $this->assertStringContainsString('<shipmentId>12345</shipmentId>', $sanitized);
    }

    public function testSanitizeXmlTruncatesScanData(): void
    {
        $rawXml = '<soap:Envelope><getShipmentScanResult><scanData>iVBORw0KGgoAAAANSUhEUgAAAXgAAAF4CAYAAABeneKmAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQU</scanData><scanMimeType>image/png</scanMimeType></getShipmentScanResult></soap:Envelope>';

        $sanitized = $this->adapter->sanitizeXml($rawXml);

        $this->assertStringNotContainsString('iVBORw0KGgoAAAANSUhEUgAAAXgAAAF4CAYAAABeneKmAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQU', $sanitized);
        $this->assertStringContainsString('<scanData>***BINARY DATA TRUNCATED***</scanData>', $sanitized);
        $this->assertStringContainsString('<scanMimeType>image/png</scanMimeType>', $sanitized);
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
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
     * Test testConnection with SoapFault failure.
     *
     * @return void
     */
    public function testTestConnectionSoapFault(): void
    {
        $soapClientStub = new TestSoapClient(true);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'wrong_pass', false);
        $result = $adapter->testConnection($credentials);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Invalid credentials', $result->getMessage());
        $this->assertEquals('AUTH_ERR', $result->getErrorCode());
    }

    /**
     * Test successful createShipments via mocked SoapClient.
     *
     * @return void
     */
    public function testCreateShipmentsSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->createShipments($credentials, [['content' => 'Test']]);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isTimeout());
        $this->assertEquals('1234567890', $result->getShipmentId());
    }

    /**
     * Test createShipments handling timeout exception.
     *
     * @return void
     */
    public function testCreateShipmentsTimeout(): void
    {
        $soapClientStub = new TestSoapClient(true);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->createShipments($credentials, [['content' => 'Test']]);

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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
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
     * Test successful getPostalCodeServices via mocked SoapClient.
     *
     * @return void
     */
    public function testGetPostalCodeServicesSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getPostalCodeServices($credentials, '00-001', '2026-08-28');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('10:00', $result->getDrPickupFrom());
        $this->assertEquals('16:00', $result->getDrPickupTo());
        $this->assertTrue($result->isPickupOnSaturday());
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->bookCourier($credentials, ['1234567890'], '2026-08-28', '10:00', '14:00');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ORDER_98765', $result->getCourierOrderId());
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->cancelCourierBooking($credentials, ['ORDER_98765']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ORDER_98765', $result->getDispatchNumber());
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
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
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub])
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
}
