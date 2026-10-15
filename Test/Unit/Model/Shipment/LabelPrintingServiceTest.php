<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use DhlPoland\Dhl\Model\Api\GetLabelsResult;
use DhlPoland\Dhl\Model\Shipment\CustomsDocumentService;
use DhlPoland\Dhl\Model\Shipment\LabelPrintingService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
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
     * @var CustomsDocumentService|Stub
     */
    private $customsDocumentServiceStub;
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
        $this->customsDocumentServiceStub = $this->createStub(CustomsDocumentService::class);

        $this->service = new LabelPrintingService(
            $this->repositoryStub,
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->labelGeneratorStub,
            $this->fileDriverStub,
            $this->customsDocumentServiceStub
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
            $this->fileDriverStub,
            $this->customsDocumentServiceStub
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
            $this->fileDriverStub,
            $this->customsDocumentServiceStub
        );

        $result = $service->getReturnLabelContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertNotEmpty($result['content']);
        $this->assertStringContainsString('dhl_labels_', $result['filename']);
    }

    public function testGetReturnLabelContentSuccessFivePackagesCombinesAllLabels(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn(
            '90040025501, 90040025502, 90040025503, 90040025504, 90040025505'
        );

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
                [
                    'shipmentId' => '90040025501',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF1',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025502',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF2',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025503',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF3',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025504',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF4',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'shipmentId' => '90040025505',
                    'labelType' => 'BLP',
                    'labelData' => 'PDF5',
                    'mimeType' => 'application/pdf',
                ],
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
            $this->fileDriverStub,
            $this->customsDocumentServiceStub
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
            new \Magento\Framework\Filesystem\Driver\File(),
            $this->customsDocumentServiceStub
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
            $fileDriverMock,
            $this->customsDocumentServiceStub
        );

        $result = $service->getReturnLabelContent([1]);

        $this->assertEquals('application/zip', $result['mimeType']);
        $this->assertEquals('MOCK_ZIP_CONTENT', $result['content']);
    }

    public function testGetLabelsContentSinglePdfWithCustomsDocumentsCombinesPdf(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getShipmentId')->willReturn('1234567890');
        $shipmentStub->method('getShipmentData')->willReturn('{"shipmentSettings":{"labelType":"BLP"}}');

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => '1234567890',
                'labelType' => 'BLP',
                'labelData' => '%PDF-LABEL-RAW',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->method('getLabels')->willReturn($labelsResult);

        $this->customsDocumentServiceStub->method('getDocumentsForShipments')
            ->willReturn([
                1 => [
                    [
                        'document_type' => CustomsDocumentService::TYPE_CN23,
                        'mime_type' => 'application/pdf',
                        'content' => '%PDF-CN23-RAW',
                    ],
                    [
                        'document_type' => CustomsDocumentService::TYPE_PROFORMA,
                        'mime_type' => 'application/pdf',
                        'content' => '%PDF-PROFORMA-RAW',
                    ],
                ],
            ]);

        $labelGeneratorMock = $this->createMock(\Magento\Shipping\Model\Shipping\LabelGenerator::class);
        $zendPdfMock = new \Zend_Pdf();
        $labelGeneratorMock->expects($this->once())
            ->method('combineLabelsPdf')
            ->with(['%PDF-LABEL-RAW', '%PDF-CN23-RAW', '%PDF-PROFORMA-RAW'])
            ->willReturn($zendPdfMock);

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $labelGeneratorMock,
            $this->fileDriverStub,
            $this->customsDocumentServiceStub
        );

        $result = $service->getLabelsContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertEquals('dhl_label_1234567890.pdf', $result['filename']);
        $this->assertNotEmpty($result['content']);
    }

    public function testGetLabelsContentSingleZplWithCustomsDocumentsZipsAll(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getShipmentId')->willReturn('1234567890');
        $shipmentStub->method('getShipmentData')->willReturn('{"shipmentSettings":{"labelType":"ZBLP"}}');

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => '1234567890',
                'labelType' => 'ZBLP',
                'labelData' => '^XA^FO50,50^ADN,36,20^FDTest^FS^XZ',
                'mimeType' => 'application/x-zpl',
            ],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->method('getLabels')->willReturn($labelsResult);

        $this->customsDocumentServiceStub->method('getDocumentsForShipments')
            ->willReturn([
                1 => [
                    [
                        'document_type' => CustomsDocumentService::TYPE_CN23,
                        'mime_type' => 'application/pdf',
                        'content' => '%PDF-CN23-RAW',
                    ],
                    [
                        'document_type' => CustomsDocumentService::TYPE_PROFORMA,
                        'mime_type' => 'application/pdf',
                        'content' => '%PDF-PROFORMA-RAW',
                    ],
                ],
            ]);

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->labelGeneratorStub,
            new \Magento\Framework\Filesystem\Driver\File(),
            $this->customsDocumentServiceStub
        );

        $result = $service->getLabelsContent([1]);

        $this->assertEquals('application/zip', $result['mimeType']);
        $this->assertEquals('dhl_label_1234567890.zip', $result['filename']);

        // Inspect zip contents
        $zip = new \ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tmpFile, $result['content']);
        $this->assertTrue($zip->open($tmpFile));
        $this->assertEquals('^XA^FO50,50^ADN,36,20^FDTest^FS^XZ', $zip->getFromName('dhl_label_1234567890.zpl'));
        $this->assertEquals('%PDF-CN23-RAW', $zip->getFromName('dhl_cn23_1234567890.pdf'));
        $this->assertEquals('%PDF-PROFORMA-RAW', $zip->getFromName('dhl_proforma_1234567890.pdf'));
        $zip->close();
        unlink($tmpFile);
    }

    public function testGetLabelsContentMultipleShipmentsAllPdfCombinesInOrder(): void
    {
        $shipment1 = $this->createStub(ShipmentDataInterface::class);
        $shipment1->method('getShipmentId')->willReturn('SHIP_1');
        $shipment2 = $this->createStub(ShipmentDataInterface::class);
        $shipment2->method('getShipmentId')->willReturn('SHIP_2');

        $this->repositoryStub->method('getById')->willReturnMap([
            [10, $shipment1],
            [20, $shipment2],
        ]);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => 'SHIP_1',
                'labelType' => 'BLP',
                'labelData' => 'PDF-LABEL-1',
                'mimeType' => 'application/pdf',
            ],
            [
                'shipmentId' => 'SHIP_2',
                'labelType' => 'BLP',
                'labelData' => 'PDF-LABEL-2',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->method('getLabels')->willReturn($labelsResult);

        $this->customsDocumentServiceStub->method('getDocumentsForShipments')
            ->willReturn([
                10 => [
                    [
                        'document_type' => CustomsDocumentService::TYPE_CN23,
                        'mime_type' => 'application/pdf',
                        'content' => 'PDF-CN23-1',
                    ],
                    [
                        'document_type' => CustomsDocumentService::TYPE_PROFORMA,
                        'mime_type' => 'application/pdf',
                        'content' => 'PDF-PROFORMA-1',
                    ],
                ],
                // SHIP_2 has no customs docs
            ]);

        $labelGeneratorMock = $this->createMock(\Magento\Shipping\Model\Shipping\LabelGenerator::class);
        $zendPdfMock = new \Zend_Pdf();
        $labelGeneratorMock->expects($this->once())
            ->method('combineLabelsPdf')
            ->with(['PDF-LABEL-1', 'PDF-CN23-1', 'PDF-PROFORMA-1', 'PDF-LABEL-2'])
            ->willReturn($zendPdfMock);

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $labelGeneratorMock,
            $this->fileDriverStub,
            $this->customsDocumentServiceStub
        );

        $result = $service->getLabelsContent([10, 20]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertStringStartsWith('dhl_labels_', $result['filename']);
        $this->assertStringEndsWith('.pdf', $result['filename']);
    }

    public function testGetLabelsContentMultipleShipmentsMixedFormatsZipsAllWithCustomsDocs(): void
    {
        $shipment1 = $this->createStub(ShipmentDataInterface::class);
        $shipment1->method('getShipmentId')->willReturn('SHIP_1');
        $shipment2 = $this->createStub(ShipmentDataInterface::class);
        $shipment2->method('getShipmentId')->willReturn('SHIP_2');

        $this->repositoryStub->method('getById')->willReturnMap([
            [10, $shipment1],
            [20, $shipment2],
        ]);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => 'SHIP_1',
                'labelType' => 'BLP',
                'labelData' => 'PDF-LABEL-1',
                'mimeType' => 'application/pdf',
            ],
            [
                'shipmentId' => 'SHIP_2',
                'labelType' => 'ZBLP',
                'labelData' => '^XA^FO50,50^FDZPL-LABEL-2^FS^XZ',
                'mimeType' => 'application/x-zpl',
            ],
        ]);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->method('getLabels')->willReturn($labelsResult);

        $this->customsDocumentServiceStub->method('getDocumentsForShipments')
            ->willReturn([
                10 => [
                    [
                        'document_type' => CustomsDocumentService::TYPE_CN23,
                        'mime_type' => 'application/pdf',
                        'content' => 'PDF-CN23-1',
                    ],
                ],
                20 => [
                    [
                        'document_type' => CustomsDocumentService::TYPE_PROFORMA,
                        'mime_type' => 'application/pdf',
                        'content' => 'PDF-PROFORMA-2',
                    ],
                ],
            ]);

        $service = new LabelPrintingService(
            $this->repositoryStub,
            $clientMock,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->createStub(\Psr\Log\LoggerInterface::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->labelGeneratorStub,
            new \Magento\Framework\Filesystem\Driver\File(),
            $this->customsDocumentServiceStub
        );

        $result = $service->getLabelsContent([10, 20]);

        $this->assertEquals('application/zip', $result['mimeType']);
        $this->assertStringStartsWith('dhl_labels_', $result['filename']);
        $this->assertStringEndsWith('.zip', $result['filename']);

        // Inspect zip contents
        $zip = new \ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tmpFile, $result['content']);
        $this->assertTrue($zip->open($tmpFile));
        $this->assertEquals('PDF-LABEL-1', $zip->getFromName('dhl_label_SHIP_1.pdf'));
        $this->assertEquals('PDF-CN23-1', $zip->getFromName('dhl_cn23_SHIP_1.pdf'));
        $this->assertEquals('^XA^FO50,50^FDZPL-LABEL-2^FS^XZ', $zip->getFromName('dhl_label_SHIP_2.zpl'));
        $this->assertEquals('PDF-PROFORMA-2', $zip->getFromName('dhl_proforma_SHIP_2.pdf'));
        $zip->close();
        unlink($tmpFile);
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
            $this->fileDriverStub,
            $this->customsDocumentServiceStub
        );
    }
}
