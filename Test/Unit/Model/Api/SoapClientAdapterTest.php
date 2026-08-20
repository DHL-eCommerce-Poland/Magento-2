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
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->adapter = new SoapClientAdapter(
            $this->loggerStub
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
            ->setConstructorArgs([$this->loggerStub])
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
            ->setConstructorArgs([$this->loggerStub])
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
            ->setConstructorArgs([$this->loggerStub])
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
            ->setConstructorArgs([$this->loggerStub])
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
}
