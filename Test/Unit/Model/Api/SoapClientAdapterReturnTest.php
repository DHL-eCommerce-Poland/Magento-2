<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\SoapClientAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SoapClientAdapterReturnTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private mixed $loggerStub;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|MockObject
     */
    private mixed $scopeConfigStub;

    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(\Magento\Framework\App\Config\ScopeConfigInterface::class);
    }

    public function testCreateShipmentReturnSingleItemSuccess(): void
    {
        $soapClientStub = new TestSoapClient(false);

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $dateSanitizer = new \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);
        $fileDriverStub = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub, $dateSanitizer, $fileDriverStub])
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

    public function testCreateShipmentReturnMultipleItemsSuccess(): void
    {
        $multiItemSoapClient = new class extends TestSoapClient {
            public function __construct()
            {
                parent::__construct(false);
            }

            public function createShipmentReturn(mixed $args): mixed
            {
                return (object) [
                    'createShipmentReturnResult' => (object) [
                        'item' => [
                            (object) [
                                'shipmentNotificationNumber' => '90040025505',
                                'shipmentTrackingNumber' => '90040025505',
                                'label' => (object) [
                                    'labelFormat' => 'application/pdf',
                                    'labelContent' => base64_encode('PDF1'),
                                ],
                            ],
                            (object) [
                                'shipmentNotificationNumber' => '90040025516',
                                'shipmentTrackingNumber' => '90040025516',
                                'label' => (object) [
                                    'labelFormat' => 'application/pdf',
                                    'labelContent' => base64_encode('PDF2'),
                                ],
                            ],
                            (object) [
                                'shipmentNotificationNumber' => '90040025527',
                                'shipmentTrackingNumber' => '90040025527',
                                'label' => (object) [
                                    'labelFormat' => 'application/pdf',
                                    'labelContent' => base64_encode('PDF3'),
                                ],
                            ],
                        ],
                    ],
                ];
            }
        };

        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $dateSanitizer = new \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);
        $fileDriverStub = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([$this->loggerStub, $this->scopeConfigStub, $dateSanitizer, $fileDriverStub])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($multiItemSoapClient);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->createShipmentReturn($credentials, ['serviceType' => 'ZK'], 1001);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(3, $result->getItems());
        $this->assertEquals(['90040025505', '90040025516', '90040025527'], $result->getShipmentIds());
        $this->assertEquals('90040025505, 90040025516, 90040025527', $result->getShipmentId());
        $this->assertEquals('90040025505, 90040025516, 90040025527', $result->getWaybill());
        $this->assertEquals('PDF1PDF2PDF3', $result->getLabelData());
        $this->assertEquals('application/pdf', $result->getLabelMimeType());
    }
}
