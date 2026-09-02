<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Model\Shipment;

use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\Credentials;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\Api\GetPnpResult;
use Ideo\Dhl\Model\Shipment\PnpReportService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

use DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PnpReportServiceTest extends TestCase
{
    private ClientInterface|Stub $clientStub;
    private CredentialsProvider|Stub $credentialsProviderStub;
    private ScopeConfigInterface|Stub $scopeConfigStub;
    private TimezoneInterface|MockObject $timezoneMock;
    private PnpReportService $service;

    protected function setUp(): void
    {
        $this->clientStub = $this->createStub(ClientInterface::class);
        $this->credentialsProviderStub = $this->createStub(CredentialsProvider::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->timezoneMock = $this->createMock(TimezoneInterface::class);

        $this->timezoneMock->method('date')
            ->willReturnCallback(fn($d) => new DateTime($d));

        $this->service = new PnpReportService(
            $this->clientStub,
            $this->credentialsProviderStub,
            $this->scopeConfigStub,
            $this->timezoneMock
        );
    }

    public function testGenerateReportSuccess(): void
    {
        $credentials = new Credentials('user', 'pass', true);

        $this->scopeConfigStub
            ->method('getValue')
            ->willReturn(true);

        $this->credentialsProviderStub
            ->method('getCredentials')
            ->willReturn($credentials);

        $result = new GetPnpResult(
            true,
            'pdf-content-bytes',
            'PNP_2026-09-01.pdf',
            'application/pdf'
        );

        $this->clientStub
            ->method('getPnp')
            ->willReturn($result);

        $report = $this->service->generateReport('2026-09-01', 'ALL');

        $this->assertEquals('pdf-content-bytes', $report['content']);
        $this->assertEquals('PNP_2026-09-01.pdf', $report['filename']);
        $this->assertEquals('application/pdf', $report['mimeType']);
    }

    public function testGenerateReportThrowsExceptionOnInvalidDate(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid date format');

        $this->service->generateReport('invalid-date', 'ALL');
    }

    public function testGenerateReportThrowsExceptionOnInvalidPackageType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid package type specified');

        $this->service->generateReport('2026-09-01', 'INVALID');
    }

    public function testGenerateReportThrowsExceptionOnApiFailure(): void
    {
        $credentials = new Credentials('user', 'pass', true);

        $this->scopeConfigStub->method('getValue')->willReturn(false);
        $this->credentialsProviderStub->method('getCredentials')->willReturn($credentials);

        $result = new GetPnpResult(false, null, null, null, 'SOAP_FAULT', 'DHL WebAPI error');
        $this->clientStub->method('getPnp')->willReturn($result);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to generate courier report: DHL WebAPI error');

        $this->service->generateReport('2026-09-01', 'EX');
    }
}
