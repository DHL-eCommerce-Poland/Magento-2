<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\SoapClientAdapter;
use DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;

class SoapClientAdapterGetPieceIdTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private mixed $loggerStub;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private mixed $scopeConfigStub;

    /**
     * @var TimezoneInterface|MockObject
     */
    private mixed $timezoneStub;

    /**
     * @var ShipmentDateSanitizer
     */
    private ShipmentDateSanitizer $dateSanitizer;

    /**
     * @var \Magento\Framework\Filesystem\Driver\File|MockObject
     */
    private mixed $fileDriverStub;

    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->timezoneStub = $this->createStub(TimezoneInterface::class);
        $this->dateSanitizer = new ShipmentDateSanitizer($this->timezoneStub);
        $this->fileDriverStub = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);
    }

    public function testGetPieceIdReturnsEmptyOnEmptyShipmentId(): void
    {
        $adapter = new SoapClientAdapter(
            $this->loggerStub,
            $this->scopeConfigStub,
            $this->dateSanitizer,
            $this->fileDriverStub
        );

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getPieceId($credentials, '');

        $this->assertEmpty($result);
    }

    public function testGetPieceIdSuccessWithAbstractPieceItems(): void
    {
        $soapClientMock = new class extends TestSoapClient {
            /**
             * @var mixed
             */
            public mixed $capturedParams = null;

            public function __construct()
            {
                parent::__construct(false);
            }

            public function getPieceId(mixed $params): stdClass
            {
                $this->capturedParams = $params;

                $item1 = new stdClass();
                $item1->blpPieceId = 'JJD000030025688110000001106';

                $item2 = new stdClass();
                $item2->blpPieceId = 'JJD000030025688110000001117';

                $pieceIdResult = new stdClass();
                $pieceIdResult->shipmentNumber = '90040027827';
                $pieceIdResult->abstractPiece = new stdClass();
                $pieceIdResult->abstractPiece->item = [$item1, $item2];

                $response = new stdClass();
                $response->getPieceIdResult = new stdClass();
                $response->getPieceIdResult->item = [$pieceIdResult];

                return $response;
            }
        };

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientMock);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getPieceId($credentials, '90040027827');

        $this->assertCount(2, $result);
        $this->assertEquals('JJD000030025688110000001106', $result[0]);
        $this->assertEquals('JJD000030025688110000001117', $result[1]);

        $this->assertIsArray($soapClientMock->capturedParams);
        $this->assertArrayHasKey('authData', $soapClientMock->capturedParams);
        $this->assertArrayHasKey('request', $soapClientMock->capturedParams);
        $this->assertEquals('user', $soapClientMock->capturedParams['authData']['username']);
        $this->assertEquals('pass', $soapClientMock->capturedParams['authData']['password']);
        $this->assertEquals(
            '90040027827',
            $soapClientMock->capturedParams['request']['items']['item'][0]['shipmentNumber']
        );
    }

    public function testGetPieceIdSuccessWithShipmentsPackagesItems(): void
    {
        $soapClientMock = new class extends TestSoapClient {
            public function __construct()
            {
                parent::__construct(false);
            }

            public function getPieceId(mixed $params): stdClass
            {
                $pkg1 = new stdClass();
                $pkg1->packageNumber = 'JJD000030025688110000001202';

                $pkg2 = new stdClass();
                $pkg2->packageNumber = 'JJD000030025688110000001213';

                $shipmentItem = new stdClass();
                $shipmentItem->shipmentNumber = '90040029352';
                $shipmentItem->packages = new stdClass();
                $shipmentItem->packages->item = [$pkg1, $pkg2];

                $response = new stdClass();
                $response->getPieceIdResult = new stdClass();
                $response->getPieceIdResult->shipments = new stdClass();
                $response->getPieceIdResult->shipments->item = [$shipmentItem];

                return $response;
            }
        };

        $adapter = $this->getMockBuilder(SoapClientAdapter::class)
            ->setConstructorArgs([
                $this->loggerStub,
                $this->scopeConfigStub,
                $this->dateSanitizer,
                $this->fileDriverStub
            ])
            ->onlyMethods(['createSoapClient'])
            ->getMock();

        $adapter->expects($this->once())
            ->method('createSoapClient')
            ->willReturn($soapClientMock);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->getPieceId($credentials, '90040029352');

        $this->assertCount(2, $result);
        $this->assertEquals('JJD000030025688110000001202', $result[0]);
        $this->assertEquals('JJD000030025688110000001213', $result[1]);
    }
}
