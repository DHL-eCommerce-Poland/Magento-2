<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Api;

use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\SoapClientAdapter;
use Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class SoapClientAdapterNearestPointsTest extends TestCase
{
    /**
     * @var Credentials
     */
    private Credentials $credentials;

    /**
     * @var LoggerInterface|MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface $scopeConfigMock;

    /**
     * @var TimezoneInterface|MockObject
     */
    private TimezoneInterface $timezoneMock;

    /**
     * @var ShipmentDateSanitizer
     */
    private ShipmentDateSanitizer $sanitizer;

    /**
     * @var \Magento\Framework\Filesystem\Driver\File|MockObject
     */
    private \Magento\Framework\Filesystem\Driver\File $fileDriverMock;

    protected function setUp(): void
    {
        $this->credentials = new Credentials('user', 'pass', true);
        $this->loggerMock = $this->createStub(LoggerInterface::class);
        $this->scopeConfigMock = $this->createStub(ScopeConfigInterface::class);
        $this->timezoneMock = $this->createStub(TimezoneInterface::class);
        $this->sanitizer = new ShipmentDateSanitizer($this->timezoneMock);
        $this->fileDriverMock = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);
    }

    public function testGetNearestServicepointsReturnsSuccessWithPoints(): void
    {
        $testSoapClient = new TestSoapClient(false);

        $adapter = new class(
            $this->loggerMock,
            $this->scopeConfigMock,
            $this->sanitizer,
            $this->fileDriverMock,
            $testSoapClient
        ) extends SoapClientAdapter {
            public function __construct(
                LoggerInterface $logger,
                ScopeConfigInterface $scopeConfig,
                ShipmentDateSanitizer $dateSanitizer,
                \Magento\Framework\Filesystem\Driver\File $fileDriver,
                private readonly TestSoapClient $testSoapClient
            ) {
                parent::__construct($logger, $scopeConfig, $dateSanitizer, $fileDriver);
            }

            protected function createSoapClient(string $wsdlUrl): \SoapClient
            {
                return $this->testSoapClient;
            }
        };

        $result = $adapter->getNearestServicepoints($this->credentials, 'PL', '00-001', 'Warszawa', 15);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->getPoints());
        $this->assertNotNull($result->getClosestPoint());
        $this->assertEquals('POP-WAW-100', $result->getClosestPoint()['sap']);
        $this->assertEquals('Sklep Żabka 100', $result->getClosestPoint()['name']);
    }

    public function testGetNearestServicepointsHandlesSoapFault(): void
    {
        $testSoapClient = new TestSoapClient(true);

        $adapter = new class(
            $this->loggerMock,
            $this->scopeConfigMock,
            $this->sanitizer,
            $this->fileDriverMock,
            $testSoapClient
        ) extends SoapClientAdapter {
            public function __construct(
                LoggerInterface $logger,
                ScopeConfigInterface $scopeConfig,
                ShipmentDateSanitizer $dateSanitizer,
                \Magento\Framework\Filesystem\Driver\File $fileDriver,
                private readonly TestSoapClient $testSoapClient
            ) {
                parent::__construct($logger, $scopeConfig, $dateSanitizer, $fileDriver);
            }

            protected function createSoapClient(string $wsdlUrl): \SoapClient
            {
                return $this->testSoapClient;
            }
        };

        $result = $adapter->getNearestServicepoints($this->credentials, 'PL', '00001');

        $this->assertFalse($result->isSuccess());
        $this->assertEmpty($result->getPoints());
        $this->assertEquals('POINTS_ERR', $result->getErrorCode());
        $this->assertStringContainsString('Error fetching nearest service points', (string) $result->getErrorMessage());
    }
}
