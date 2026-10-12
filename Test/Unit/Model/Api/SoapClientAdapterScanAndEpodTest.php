<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\SoapClientAdapter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SoapClientAdapterScanAndEpodTest extends TestCase
{
    /**
     * @var Stub
     */
    private $loggerStub;
    /**
     * @var Stub
     */
    private $scopeConfigStub;
    /**
     * @var Stub
     */
    private $timezoneStub;
    /**
     * @var \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer
     */
    private $dateSanitizer;
    /**
     * @var Credentials
     */
    private $credentials;
    /**
     * @var Stub
     */
    private $fileDriverStub;

    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->timezoneStub = $this->createStub(TimezoneInterface::class);
        $this->dateSanitizer = new \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer($this->timezoneStub);
        $this->fileDriverStub = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);
        $this->credentials = new Credentials('user', 'pass', true);
    }

    public function testGetShipmentScanSuccess(): void
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

        $result = $adapter->getShipmentScan($this->credentials, '1234567890');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('dummy_scan_pdf_content', $result->getFileData());
        $this->assertEquals('application/pdf', $result->getMimeType());
    }

    public function testGetShipmentScanSoapFault(): void
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

        $result = $adapter->getShipmentScan($this->credentials, '1234567890');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('SCAN_ERR', $result->getErrorCode());
        $this->assertStringContainsString('Error fetching shipment scan', $result->getErrorMessage());
    }

    public function testGetEpodSuccess(): void
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

        $result = $adapter->getEpod($this->credentials, '1234567890');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('dummy_epod_pdf_content', $result->getFileData());
        $this->assertEquals('application/pdf', $result->getMimeType());
    }

    public function testGetEpodSoapFault(): void
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

        $result = $adapter->getEpod($this->credentials, '1234567890');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('EPOD_ERR', $result->getErrorCode());
        $this->assertStringContainsString('Error fetching ePOD', $result->getErrorMessage());
    }
}
