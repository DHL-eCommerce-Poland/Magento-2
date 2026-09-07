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
    private ShipmentDataRepositoryInterface|Stub $repositoryStub;
    private ClientInterface|Stub $clientStub;
    private CredentialsProvider|Stub $credentialsProviderStub;
    private ScopeConfigInterface|Stub $scopeConfigStub;
    private LabelPrintingService $service;

    protected function setUp(): void
    {
        $this->repositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);

        $this->service = new LabelPrintingService(
            $this->repositoryStub,
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->scopeConfigStub
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
        $shipmentStub->method('getShipmentData')->willReturn(json_encode(['labelType' => 'BLP']));

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->scopeConfigStub->method('getValue')->willReturn('1');
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => '1234567890',
                'labelType' => 'BLP',
                'labelData' => '%PDF-1.4 dummy binary content',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $this->clientStub->method('getLabels')->willReturn($labelsResult);

        $result = $this->service->getLabelsContent([1]);

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
            $labelGeneratorStub
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

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => 'RET1234567890',
                'labelType' => 'BLP',
                'labelData' => '%PDF-1.4 dummy return label content',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $this->clientStub->method('getLabels')->willReturn($labelsResult);

        $result = $this->service->getReturnLabelContent([1]);

        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertEquals('%PDF-1.4 dummy return label content', $result['content']);
        $this->assertEquals('dhl_return_label_RET1234567890.pdf', $result['filename']);
    }

    public function testGetReturnQrCodeContentSuccessSingleQrCode(): void
    {
        $shipmentStub = $this->createStub(ShipmentDataInterface::class);
        $shipmentStub->method('getReturnShipmentId')->willReturn('RET1234567890');

        $this->repositoryStub->method('getById')->willReturn($shipmentStub);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $labelsResult = new GetLabelsResult(true, [
            [
                'shipmentId' => 'RET1234567890',
                'labelType' => 'QR_PDF',
                'labelData' => '%PDF-1.4 dummy return QR code content',
                'mimeType' => 'application/pdf',
            ],
        ]);

        $this->clientStub->method('getLabels')->willReturn($labelsResult);

        $result = $this->service->getReturnQrCodeContent([1]);

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
}
