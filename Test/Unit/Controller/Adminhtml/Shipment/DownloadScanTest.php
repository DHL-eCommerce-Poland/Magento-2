<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Ideo\Dhl\Controller\Adminhtml\Shipment\DownloadScan;
use Ideo\Dhl\Model\Shipment\DocumentDownloadService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DownloadScanTest extends TestCase
{
    private Context|Stub $contextStub;
    private RequestInterface|Stub $requestStub;
    private RedirectFactory|Stub $redirectFactoryStub;
    private Redirect|Stub $redirectStub;
    private ManagerInterface|Stub $messageManagerStub;
    private DocumentDownloadService|Stub $serviceStub;
    private FileFactory|Stub $fileFactoryStub;
    private DownloadScan $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->messageManagerStub = $this->createStub(ManagerInterface::class);
        $this->serviceStub = $this->createStub(DocumentDownloadService::class);
        $this->fileFactoryStub = $this->createStub(FileFactory::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($this->redirectFactoryStub);
        $this->contextStub->method('getMessageManager')->willReturn($this->messageManagerStub);
        $this->redirectFactoryStub->method('create')->willReturn($this->redirectStub);

        $this->controller = new DownloadScan(
            $this->contextStub,
            $this->serviceStub,
            $this->fileFactoryStub
        );
    }

    public function testExecuteWithInvalidEntityIdRedirects(): void
    {
        $this->requestStub->method('getParam')->willReturn(0);
        $this->redirectStub->method('setPath')->willReturnSelf();

        $this->assertSame($this->redirectStub, $this->controller->execute());
    }

    public function testExecuteSuccessStreamsFile(): void
    {
        $this->requestStub->method('getParam')->willReturn(10);

        $scanData = [
            'content' => 'binary_pdf_content',
            'mimeType' => 'application/pdf',
            'filename' => 'dhl_scan_1234567890.pdf',
        ];

        $this->serviceStub->method('getScanContent')->willReturn($scanData);

        $responseStub = $this->createStub(ResponseInterface::class);
        $this->fileFactoryStub->method('create')->willReturn($responseStub);

        $result = $this->controller->execute();
        $this->assertSame($responseStub, $result);
    }

    public function testExecuteServiceExceptionRedirectsWithError(): void
    {
        $this->requestStub->method('getParam')->willReturn(10);
        $this->serviceStub->method('getScanContent')->willThrowException(new LocalizedException(__('Scan error')));
        $this->redirectStub->method('setPath')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }
}
