<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use DhlPoland\Dhl\Controller\Adminhtml\Shipment\GeneratePnpReport;
use DhlPoland\Dhl\Model\Shipment\PnpReportService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class GeneratePnpReportTest extends TestCase
{
    /**
     * @var Stub
     */
    private $contextStub;
    /**
     * @var Stub
     */
    private $requestStub;
    /**
     * @var Stub
     */
    private $redirectFactoryStub;
    /**
     * @var Stub
     */
    private $redirectStub;
    /**
     * @var Stub
     */
    private $messageManagerStub;
    /**
     * @var MockObject
     */
    private $serviceMock;
    /**
     * @var Stub
     */
    private $fileFactoryStub;
    /**
     * @var Stub
     */
    private $timezoneStub;
    /**
     * @var GeneratePnpReport
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->messageManagerStub = $this->createStub(ManagerInterface::class);
        $this->serviceMock = $this->createMock(PnpReportService::class);
        $this->fileFactoryStub = $this->createStub(FileFactory::class);
        $this->timezoneStub = $this->createStub(TimezoneInterface::class);
        $this->timezoneStub->method('scopeDate')->willReturn(new \DateTime('2026-09-02'));

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($this->redirectFactoryStub);
        $this->contextStub->method('getMessageManager')->willReturn($this->messageManagerStub);
        $this->redirectFactoryStub->method('create')->willReturn($this->redirectStub);

        $this->controller = new GeneratePnpReport(
            $this->contextStub,
            $this->serviceMock,
            $this->fileFactoryStub,
            $this->timezoneStub
        );
    }

    public function testExecuteSuccessStreamsFile(): void
    {
        $this->requestStub->method('getParam')->willReturnCallback(function ($param, $default = null) {
            if ($param === 'pnp') {
                return ['date' => '2026-09-01', 'typePackage' => 'ALL'];
            }
            return $default;
        });

        $reportData = [
            'content' => 'pdf_binary_content',
            'mimeType' => 'application/pdf',
            'filename' => 'PNP_2026-09-01.pdf',
        ];

        $this->serviceMock->expects($this->once())
            ->method('generateReport')
            ->with('2026-09-01', 'ALL')
            ->willReturn($reportData);

        $responseStub = $this->createStub(ResponseInterface::class);
        $this->fileFactoryStub->method('create')->willReturn($responseStub);

        $result = $this->controller->execute();
        $this->assertSame($responseStub, $result);
    }

    public function testExecuteServiceExceptionRedirectsWithError(): void
    {
        $this->requestStub->method('getParam')->willReturn(null);
        $this->serviceMock->expects($this->once())
            ->method('generateReport')
            ->willThrowException(new LocalizedException(__('PnP report error')));
        $this->redirectStub->method('setPath')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }
}
