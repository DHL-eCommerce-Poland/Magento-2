<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Api;

use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\SoapClientAdapter;
use DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SoapClientAdapterCustomsTest extends TestCase
{
    private LoggerInterface $loggerStub;
    private ScopeConfigInterface $scopeConfigStub;
    private ShipmentDateSanitizer $dateSanitizer;
    private FileDriver $fileDriverStub;

    protected function setUp(): void
    {
        $this->loggerStub = $this->createStub(LoggerInterface::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $timezoneStub = $this->createStub(TimezoneInterface::class);
        $timezoneStub->method('scopeDate')
            ->willReturn(new \DateTime('2026-09-02'));
        $this->dateSanitizer = new ShipmentDateSanitizer($timezoneStub);
        $this->fileDriverStub = $this->createStub(FileDriver::class);
    }

    /**
     * Test createShipment parsing of customs documents inside label object.
     *
     * @return void
     */
    public function testCreateShipmentExtractsCustomsDocumentsFromLabelObject(): void
    {
        $fakeResponse = (object) [
            'createShipmentResult' => (object) [
                'shipmentNotificationNumber' => '90040340516',
                'shipmentTrackingNumber' => 'CY830803878DE',
                'label' => (object) [
                    'labelType' => 'BLP',
                    'labelFormat' => 'application/pdf',
                    'labelContent' => base64_encode('PDF_LABEL_DATA'),
                    'cn23Content' => base64_encode('PDF_CN23_DATA'),
                    'cn23MimeType' => 'application/pdf',
                    'fvProformaContent' => base64_encode('PDF_PROFORMA_DATA'),
                    'fvProformaMimeType' => 'application/pdf',
                    'fvProformaNumer' => 'DHL-FV-900003-10-2026',
                ],
            ],
        ];

        $soapClientMock = new class($fakeResponse) extends TestSoapClient {
            public function __construct(private readonly object $response)
            {
                parent::__construct(false);
            }

            public function createShipment(mixed $args): object
            {
                return $this->response;
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
            ->willReturn($soapClientMock);

        $credentials = new Credentials('user', 'pass', true);
        $result = $adapter->createShipment($credentials, ['content' => 'Customs shipment']);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('90040340516', $result->getShipmentId());
        $this->assertEquals(base64_encode('PDF_LABEL_DATA'), $result->getLabelContent());
        $this->assertEquals('application/pdf', $result->getLabelFormat());
        $this->assertEquals('BLP', $result->getLabelType());
        $this->assertTrue($result->hasCustomsDocuments());
        $this->assertEquals(base64_encode('PDF_CN23_DATA'), $result->getCn23Content());
        $this->assertEquals('application/pdf', $result->getCn23MimeType());
        $this->assertEquals(base64_encode('PDF_PROFORMA_DATA'), $result->getFvProformaContent());
        $this->assertEquals('application/pdf', $result->getFvProformaMimeType());
        $this->assertEquals('DHL-FV-900003-10-2026', $result->getFvProformaNumber());
    }

    /**
     * Test sanitizeXml masks cn23Content and fvProformaContent binary tags.
     *
     * @return void
     */
    public function testSanitizeXmlMasksCn23AndFvProformaContent(): void
    {
        $adapter = new SoapClientAdapter(
            $this->loggerStub,
            $this->scopeConfigStub,
            $this->dateSanitizer,
            $this->fileDriverStub
        );

        $xml = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<SOAP-ENV:Body>'
            . '<label>'
            . '<cn23Content>JVBERi0xLjUKJeLjz9MKNSAwIG9iago8PC9TdWJ0eXBlL0Zvcm0vRmlsdGVyL0ZsYXRlRGVjb2Rl</cn23Content>'
            . '<fvProformaContent>JVBERi0xLjUKJeLjz9MKNiAwIG9iago8PC9GaWx0ZXIvRmxhdGVEZWNvZGUvTGVuZ3RoIDI2</fvProformaContent>'
            . '</label>'
            . '</SOAP-ENV:Body>'
            . '</SOAP-ENV:Envelope>';

        $sanitized = $adapter->sanitizeXml($xml);

        $this->assertStringNotContainsString('JVBERi0xLjUK', $sanitized);
        $this->assertStringContainsString('<cn23Content>***BINARY DATA TRUNCATED***</cn23Content>', $sanitized);
        $this->assertStringContainsString('<fvProformaContent>***BINARY DATA TRUNCATED***</fvProformaContent>', $sanitized);
    }
}
