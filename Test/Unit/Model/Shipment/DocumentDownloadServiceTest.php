<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Model\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\Credentials;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use DhlPoland\Dhl\Model\Api\GetEpodResult;
use DhlPoland\Dhl\Model\Api\GetScanResult;
use DhlPoland\Dhl\Model\Shipment\DocumentDownloadService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DocumentDownloadServiceTest extends TestCase
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
    private $filesystemStub;
    /**
     * @var Stub
     */
    private $labelGeneratorStub;
    /**
     * @var Stub
     */
    private $loggerStub;
    /**
     * @var DocumentDownloadService
     */
    private $service;

    protected function setUp(): void
    {
        $this->repositoryStub = $this->createStub(ShipmentDataRepositoryInterface::class);
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->filesystemStub = $this->createStub(Filesystem::class);
        $this->labelGeneratorStub = $this->createStub(LabelGenerator::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $credentials = new Credentials('user', 'pass', true);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $this->service = new DocumentDownloadService(
            $this->repositoryStub,
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->filesystemStub,
            $this->labelGeneratorStub,
            $this->loggerStub
        );
    }

    public function testGetScanContentThrowsExceptionForInvalidEntityId(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid shipment entity ID.');
        $this->service->getScanContent(0);
    }

    public function testGetScanContentThrowsExceptionWhenShipmentHasNoDhlId(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn(null);

        $this->repositoryStub->method('getById')->willReturn($shipment);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Shipment entity #1 does not have a DHL shipment ID.');
        $this->service->getScanContent(1);
    }

    public function testGetScanContentSuccess(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $this->repositoryStub->method('getById')->willReturn($shipment);

        $scanResult = new GetScanResult(true, 'pdf_scan_data', 'application/pdf');
        $this->clientStub->method('getShipmentScan')->willReturn($scanResult);

        $result = $this->service->getScanContent(1);

        $this->assertEquals('pdf_scan_data', $result['content']);
        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertEquals('dhl_scan_1234567890.pdf', $result['filename']);
    }

    public function testGetScanContentThrowsExceptionOnApiFailure(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $this->repositoryStub->method('getById')->willReturn($shipment);

        $scanResult = new GetScanResult(false, null, null, 'NOT_FOUND', 'Scan not found');
        $this->clientStub->method('getShipmentScan')->willReturn($scanResult);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to retrieve waybill scan from DHL: Scan not found');
        $this->service->getScanContent(1);
    }

    public function testGetEpodContentSuccess(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $this->repositoryStub->method('getById')->willReturn($shipment);

        $epodResult = new GetEpodResult(true, 'epod_data', 'application/pdf');
        $this->clientStub->method('getEpod')->willReturn($epodResult);

        $result = $this->service->getEpodContent(1);

        $this->assertEquals('epod_data', $result['content']);
        $this->assertEquals('application/pdf', $result['mimeType']);
        $this->assertEquals('dhl_epod_1234567890.pdf', $result['filename']);
    }

    public function testGetEpodContentThrowsExceptionOnApiFailure(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('1234567890');
        $this->repositoryStub->method('getById')->willReturn($shipment);

        $epodResult = new GetEpodResult(false, null, null, 'NO_EPOD', 'ePOD not available');
        $this->clientStub->method('getEpod')->willReturn($epodResult);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to retrieve ePOD from DHL: ePOD not available');
        $this->service->getEpodContent(1);
    }

    public function testGetEpodContentDetectsPngMagicBytesWhenApiClaimsPdf(): void
    {
        $shipment = $this->createStub(ShipmentDataInterface::class);
        $shipment->method('getShipmentId')->willReturn('90039577583');
        $this->repositoryStub->method('getById')->willReturn($shipment);

        $pngBinaryData = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR...";
        $epodResult = new GetEpodResult(true, $pngBinaryData, 'application/pdf');
        $this->clientStub->method('getEpod')->willReturn($epodResult);

        $result = $this->service->getEpodContent(1);

        $this->assertEquals('image/png', $result['mimeType']);
        $this->assertEquals('dhl_epod_90039577583.png', $result['filename']);
        $this->assertEquals($pngBinaryData, $result['content']);
    }
}
