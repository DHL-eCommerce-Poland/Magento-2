<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\GetLabelsResult;
use Ideo\Dhl\Model\Shipment\LabelPrintingService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class LabelPrintingServiceTest extends TestCase
{
    /**
     * @var Stub
     */
    private $repositoryStub;
    /**
     * @var Stub
     */
    private $clientStub;
    /**
     * @var Stub
     */
    private $credentialsProviderStub;
    /**
     * @var Stub
     */
    private $scopeConfigStub;
    /**
     * @var \Magento\Shipping\Model\Shipping\LabelGenerator|Stub
     */
    private $labelGeneratorStub;
    /**
     * @var \Magento\Framework\Filesystem\Driver\File|Stub
     */
    private $fileDriverStub;
    /**
     * @var LabelPrintingService
     */
    private $service;

    protected function setUp(): void
    {
        $this->repositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->labelGeneratorStub = $this->createStub(\Magento\Shipping\Model\Shipping\LabelGenerator::class);
        $this->fileDriverStub = $this->createStub(\Magento\Framework\Filesystem\Driver\File::class);

        $this->service = new LabelPrintingService(
            $this->repositoryStub,
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->labelGeneratorStub,
            $this->fileDriverStub
        );
    }

    public function testGetLabelsContentThrowsExceptionWhenNoIds(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No shipments selected for label printing.');

        $this->service->getLabelsContent([]);
    }

    public function testGetLabelsContentSuccessSingleLabel(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getShipmentId')->willReturn('1234567890');
        $shipmentStub->method('getShipmentData')->willReturn(
            '{"shipmentSettings":{"labelType":"ZBLP300"}}'
        );

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->scopeConfigStub->method('getValue')->willReturn('1');
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => '1234567890',
                'labelType' => 'ZBLP300',
                'labelData' => '%PDF-1.4 dummy binary content',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('getLabels')
            ->with($credentials, [['shipmentId' => '1234567890', 'labelType' => 'ZBLP300']])
            ->willReturn($labelsResult);

        $service = $this->createServiceWithClient($clientMock);
        $result = $service->getLabelsContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertEquals('%PDF-1.4 dummy binary content', $result['content']);
        $this->assertEquals('dhl_label_1234567890.pdf', $result['filename']);
    }

    public function testGetLabelsContentThrowsExceptionOnApiError(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getShipmentId')->willReturn('1234567890');

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(false, [], 'SOAP_FAULT', 'Shipment not found');
        $this->clientStub->method('getLabels')->willReturn($labelsResult);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to retrieve labels from DHL: Shipment not found');

        $this->service->getLabelsContent([1]);
    }

    public function testGetLabelsContentMultipleLabelsCombinesContent(): void
    {
        $shipmentStub1 = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub1->method('getShipmentId')->willReturn('10001');

        $shipmentStub2 = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub2->method('getShipmentId')->willReturn('10002');

        $this->repositoryStub->method('getById')
            ->willReturnMap([
                [1, $shipmentStub1],
                [2, $shipmentStub2],
            ]);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => '10001',
                'labelType' => 'BLP',
                'labelData' => 'PDF_CONTENT_1',
                'mimeType' => 'application/pdf',
            ],
            [
                'shipmentId' => '10002',
                'labelType' => 'BLP',
                'labelData' => 'PDF_CONTENT_2',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $this->clientStub->method('getLabels')->willReturn($labelsResult);

        $labelGeneratorStub = $this->createStub(\Magento\Shipping\Model\Shipping\LabelGenerator::class);
        $zendPdfMock = new \Zend_Pdf();
        $labelGeneratorStub->method('combineLabelsPdf')->willReturn($zendPdfMock);

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $labelGeneratorStub,
            $this->fileDriverStub
        );

        $result = $service->getLabelsContent([1, 2]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertStringContainsString('dhl_labels_', $result['filename']);
        $this->assertNotEmpty($result['content']);
    }

    public function testGetReturnLabelContentSuccessSingleLabel(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn('RET1234567890');
        $shipmentStub->method('getShipmentData')->willReturn(
            '{"shipmentSettings":{"labelType":"LBLP","returnLabelType":"ZBLP300"}}'
        );

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => 'RET1234567890',
                'labelType' => 'ZBLP300',
                'labelData' => '%PDF-1.4 dummy return label content',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('getLabels')
            ->with($credentials, [['shipmentId' => 'RET1234567890', 'labelType' => 'ZBLP300']])
            ->willReturn($labelsResult);

        $service = $this->createServiceWithClient($clientMock);
        $result = $service->getReturnLabelContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertEquals('%PDF-1.4 dummy return label content', $result['content']);
        $this->assertEquals('dhl_return_label_RET1234567890.pdf', $result['filename']);
    }

    public function testGetReturnQrCodeContentSuccessSingleQrCode(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn('RET1234567890');
        $shipmentStub->method('getShipmentData')->willReturn(
            '{"shipmentSettings":{"returnQrCodeType":"QR4_IMG"}}'
        );

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => 'RET1234567890',
                'labelType' => 'QR4_IMG',
                'labelData' => '%PDF-1.4 dummy return QR code content',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('getLabels')
            ->with($credentials, [['shipmentId' => 'RET1234567890', 'labelType' => 'QR4_IMG']])
            ->willReturn($labelsResult);

        $service = $this->createServiceWithClient($clientMock);
        $result = $service->getReturnQrCodeContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertEquals('%PDF-1.4 dummy return QR code content', $result['content']);
        $this->assertEquals('dhl_return_qr_RET1234567890.pdf', $result['filename']);
    }

    public function testGetReturnLabelContentThrowsExceptionWhenNoValidReturnShipmentId(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn(null);

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('None of the selected shipments have valid DHL return shipment IDs.');

        $this->service->getReturnLabelContent([1]);
    }

    public function testGetReturnLabelContentSuccessMultipleReturnShipmentIds(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn('90040025505, 90040025516, 90040025527');

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('getLabels')
            ->with($credentials, [
                ['shipmentId' => '90040025505', 'labelType' => 'BLP'],
                ['shipmentId' => '90040025516', 'labelType' => 'BLP'],
                ['shipmentId' => '90040025527', 'labelType' => 'BLP'],
            ])
            ->willReturn(new GetLabelsResult(true, [
                [
                    'shipmentId' => '90040025505',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF1',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025516',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF2',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025527',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF3',
                    'mimeType' => 'application/pdf',
                ],
            ]));

        $labelGeneratorStub = $this->createStub(\Magento\Shipping\Model\Shipping\LabelGenerator::class);
        $zendPdfMock = new \Zend_Pdf();
        $labelGeneratorStub->method('combineLabelsPdf')->willReturn($zendPdfMock);

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $labelGeneratorStub,
            $this->fileDriverStub
        );

        $result = $service->getReturnLabelContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertNotEmpty($result['content']);
        $this->assertStringContainsString('dhl_labels_', $result['filename']);
    }

    public function testGetReturnLabelContentSuccessFivePackagesCombinesAllLabels(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn('90040025501, 90040025502, 90040025503, 90040025504, 90040025505');

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('getLabels')
            ->with($credentials, [
                ['shipmentId' => '90040025501', 'labelType' => 'BLP'],
                ['shipmentId' => '90040025502', 'labelType' => 'BLP'],
                ['shipmentId' => '90040025503', 'labelType' => 'BLP'],
                ['shipmentId' => '90040025504', 'labelType' => 'BLP'],
                ['shipmentId' => '90040025505', 'labelType' => 'BLP'],
            ])
            ->willReturn(new GetLabelsResult(true, [
                ['shipmentId' => '90040025501', 'labelType' => 'BLP', 'labelData' => 'PDF1', 'mimeType' => 'application/pdf'],
                ['shipmentId' => '90040025502', 'labelType' => 'BLP', 'labelData' => 'PDF2', 'mimeType' => 'application/pdf'],
                ['shipmentId' => '90040025503', 'labelType' => 'BLP', 'labelData' => 'PDF3', 'mimeType' => 'application/pdf'],
                ['shipmentId' => '90040025504', 'labelType' => 'BLP', 'labelData' => 'PDF4', 'mimeType' => 'application/pdf'],
                ['shipmentId' => '90040025505', 'labelType' => 'BLP', 'labelData' => 'PDF5', 'mimeType' => 'application/pdf'],
            ]));

        $labelGeneratorMock = $this->createMock(\Magento\Shipping\Model\Shipping\LabelGenerator::class);
        $zendPdfMock = new \Zend_Pdf();
        $labelGeneratorMock->expects($this->once())
            ->method('combineLabelsPdf')
            ->with(['PDF1', 'PDF2', 'PDF3', 'PDF4', 'PDF5'])
            ->willReturn($zendPdfMock);

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $labelGeneratorMock,
            $this->fileDriverStub
        );

        $result = $service->getReturnLabelContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertNotEmpty($result['content']);
        $this->assertStringContainsString('dhl_labels_', $result['filename']);
    }

    public function testGetReturnLabelContentSuccessMultiplePngLabelsZipped(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn('90040025505, 90040025516');

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('getLabels')
            ->willReturn(new GetLabelsResult(true, [
                [
                    'shipmentId' => '90040025505',
                    'labelType' => 'PNG',
                    'labelData' => 'PNG_BYTES_1',
                    'mimeType' => 'image/png',
                ],
                [
                    'shipmentId' => '90040025516',
                    'labelType' => 'PNG',
                    'labelData' => 'PNG_BYTES_2',
                    'mimeType' => 'image/png',
                ],
            ]));

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->labelGeneratorStub,
            new \Magento\Framework\Filesystem\Driver\File()
        );

        $result = $service->getReturnLabelContent([1]);

        $this->assertEquals('application/zip', $result['mimeType']);
        $this->assertNotEmpty($result['content']);
        $this->assertStringEndsWith('.zip', $result['filename']);
    }

    public function testGetReturnLabelContentUsesFileDriverAndCleansUp(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn('90040025505, 90040025516');
        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('getLabels')
            ->willReturn(new GetLabelsResult(true, [
                [
                    'shipmentId' => '90040025505',
                    'labelType' => 'PNG',
                    'labelData' => 'PNG_BYTES_1',
                    'mimeType' => 'image/png',
                ],
                [
                    'shipmentId' => '90040025516',
                    'labelType' => 'PNG',
                    'labelData' => 'PNG_BYTES_2',
                    'mimeType' => 'image/png',
                ],
            ]));

        $fileDriverMock = $this->createMock(\Magento\Framework\Filesystem\Driver\File::class);
        $fileDriverMock->expects($this->once())
            ->method('fileGetContents')
            ->willReturn('MOCK_ZIP_CONTENT');
        $fileDriverMock->expects($this->once())
            ->method('isExists')
            ->willReturn(true);
        $fileDriverMock->expects($this->once())
            ->method('deleteFile')
            ->with($this->callback('is_string'));

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->labelGeneratorStub,
            $fileDriverMock
        );

        $result = $service->getReturnLabelContent([1]);

        $this->assertEquals('application/zip', $result['mimeType']);
        $this->assertEquals('MOCK_ZIP_CONTENT', $result['content']);
    }

    /**
     * Create a label printing service with a test-specific DHL client boundary.
     *
     * @param ClientInterface $client
     *
     * @return LabelPrintingService
     */
    private function createServiceWithClient(ClientInterface $client): LabelPrintingService
    {
        return new LabelPrintingService(
            $this->repositoryStub,
            $client,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->labelGeneratorStub,
            $this->fileDriverStub
        );
    }
}
