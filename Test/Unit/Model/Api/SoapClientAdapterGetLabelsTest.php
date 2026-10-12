<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\SoapClientAdapter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SoapClientAdapterGetLabelsTest extends TestCase
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
     * @var \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer
     */
    private $dateSanitizer;

    /**
     * @var Stub
     */
    private $fileDriverStub;

    /**
     * @var Credentials
     */
    private $credentials;

    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $timezoneStub = $this->createStub(\Magento\Framework\Stdlib\DateTime\TimezoneInterface::class);
        $this->dateSanitizer = new \DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer($timezoneStub);
        $this->fileDriverStub = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);
        $this->credentials = new Credentials('user', 'pass', true);
    }

    public function testGetLabelsReturnsInvalidInputWhenNoValidShipments(): void
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

        $result = $adapter->getLabels($this->credentials, []);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('INVALID_INPUT', $result->getErrorCode());
        $this->assertEquals(0, $soapClientStub->getLabelsCallCount);
    }

    public function testGetLabelsSingleItemMakesOneSoapCall(): void
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

        $itemsToPrint = [
            ['shipmentId' => '10001', 'labelType' => 'BLP'],
        ];

        $result = $adapter->getLabels($this->credentials, $itemsToPrint);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $soapClientStub->getLabelsCallCount);
        $this->assertCount(1, $result->getItems());
        $this->assertEquals('10001', $result->getItems()[0]['shipmentId']);
        $this->assertEquals('%PDF-1.4 dummy label for 10001', $result->getItems()[0]['labelData']);
    }

    public function testGetLabelsThreeItemsMakesOneSoapCall(): void
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

        $itemsToPrint = [
            ['shipmentId' => '10001', 'labelType' => 'BLP'],
            ['shipmentId' => '10002', 'labelType' => 'BLP'],
            ['shipmentId' => '10003', 'labelType' => 'BLP'],
        ];

        $result = $adapter->getLabels($this->credentials, $itemsToPrint);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(1, $soapClientStub->getLabelsCallCount);
        $this->assertCount(3, $result->getItems());
        $this->assertCount(3, $soapClientStub->getLabelsCalls[0]['itemsToPrint']);
    }

    public function testGetLabelsFiveItemsChunksIntoTwoSoapCalls(): void
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

        $itemsToPrint = [
            ['shipmentId' => '10001', 'labelType' => 'BLP'],
            ['shipmentId' => '10002', 'labelType' => 'BLP'],
            ['shipmentId' => '10003', 'labelType' => 'BLP'],
            ['shipmentId' => '10004', 'labelType' => 'BLP'],
            ['shipmentId' => '10005', 'labelType' => 'BLP'],
        ];

        $result = $adapter->getLabels($this->credentials, $itemsToPrint);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(2, $soapClientStub->getLabelsCallCount);
        $this->assertCount(5, $result->getItems());

        // Verify first chunk had 3 items and second chunk had 2 items
        $this->assertCount(3, $soapClientStub->getLabelsCalls[0]['itemsToPrint']);
        $this->assertEquals('10001', $soapClientStub->getLabelsCalls[0]['itemsToPrint'][0]['shipmentId']);
        $this->assertEquals('10002', $soapClientStub->getLabelsCalls[0]['itemsToPrint'][1]['shipmentId']);
        $this->assertEquals('10003', $soapClientStub->getLabelsCalls[0]['itemsToPrint'][2]['shipmentId']);

        $this->assertCount(2, $soapClientStub->getLabelsCalls[1]['itemsToPrint']);
        $this->assertEquals('10004', $soapClientStub->getLabelsCalls[1]['itemsToPrint'][0]['shipmentId']);
        $this->assertEquals('10005', $soapClientStub->getLabelsCalls[1]['itemsToPrint'][1]['shipmentId']);
    }

    public function testGetLabelsSevenItemsChunksIntoThreeSoapCalls(): void
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

        $itemsToPrint = [
            ['shipmentId' => '10001', 'labelType' => 'QR_PDF'],
            ['shipmentId' => '10002', 'labelType' => 'QR_PDF'],
            ['shipmentId' => '10003', 'labelType' => 'QR_PDF'],
            ['shipmentId' => '10004', 'labelType' => 'QR_PDF'],
            ['shipmentId' => '10005', 'labelType' => 'QR_PDF'],
            ['shipmentId' => '10006', 'labelType' => 'QR_PDF'],
            ['shipmentId' => '10007', 'labelType' => 'QR_PDF'],
        ];

        $result = $adapter->getLabels($this->credentials, $itemsToPrint);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(3, $soapClientStub->getLabelsCallCount);
        $this->assertCount(7, $result->getItems());
        $this->assertCount(3, $soapClientStub->getLabelsCalls[0]['itemsToPrint']);
        $this->assertCount(3, $soapClientStub->getLabelsCalls[1]['itemsToPrint']);
        $this->assertCount(1, $soapClientStub->getLabelsCalls[2]['itemsToPrint']);
    }

    public function testGetLabelsHandlesSoapFault(): void
    {
        $soapClientStub = new TestSoapClient(true, 'DHL API error');

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

        $itemsToPrint = [
            ['shipmentId' => '10001', 'labelType' => 'BLP'],
        ];

        $result = $adapter->getLabels($this->credentials, $itemsToPrint);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('GET_LABELS_ERR', $result->getErrorCode());
        $this->assertStringContainsString('DHL API error', (string) $result->getErrorMessage());
    }
}
