<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\SoapClientAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SoapClientAdapterReturnTest extends TestCase
{
    private LoggerInterface|MockObject $loggerStub;
    private \Magento\Framework\App\Config\ScopeConfigInterface|MockObject $scopeConfigStub;

    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(\Magento\Framework\App\Config\ScopeConfigInterface::class);
    }

    public function testCreateShipmentReturnSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub, $timezoneStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientStub);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->createShipmentReturn($credentials, ['serviceType' => 'ZK'], 1001);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isTimeout());
        $this->assertEquals('RET987654321', $result->getShipmentId());
        $this->assertEquals('RET987654321', $result->getWaybill());
        $this->assertNotNull($result->getLabelData());
        $this->assertEquals('application/pdf', $result->getLabelMimeType());
    }
}
