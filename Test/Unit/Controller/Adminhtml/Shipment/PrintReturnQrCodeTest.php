<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Controller\Adminhtml\Shipment\PrintReturnQrCode;
use Ideo\Dhl\Model\Shipment\LabelPrintingService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PrintReturnQrCodeTest extends TestCase
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
     * @var Stub
     */
    private $labelPrintingServiceStub;
    /**
     * @var Stub
     */
    private $fileFactoryStub;
    /**
     * @var Stub
     */
    private $jsonFactoryStub;
    /**
     * @var PrintReturnQrCode
     */
    private $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(HttpRequest::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->redirectStub->method('setPath')->willReturnSelf();
        $this->messageManagerStub = $this->createStub(ManagerInterface::class);
        $this->labelPrintingServiceStub = $this->createStub(LabelPrintingService::class);
        $this->fileFactoryStub = $this->createStub(FileFactory::class);
        $this->jsonFactoryStub = $this->createStub(JsonFactory::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($this->redirectFactoryStub);
        $this->contextStub->method('getMessageManager')->willReturn($this->messageManagerStub);
        $this->redirectFactoryStub->method('create')->willReturn($this->redirectStub);

        $this->controller = new PrintReturnQrCode(
            $this->contextStub,
            $this->labelPrintingServiceStub,
            $this->fileFactoryStub,
            $this->jsonFactoryStub
        );
    }

    public function testExecuteWithInvalidEntityIdRedirects(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(false);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 0 : null;
        });

        $calledPath = null;
        $this->redirectStub->method('setPath')->willReturnCallback(function (string $path) use (&$calledPath) {
            $calledPath = $path;
            return $this->redirectStub;
        });

        $this->assertSame($this->redirectStub, $this->controller->execute());
        $this->assertEquals('*/*/index', $calledPath);
    }

    public function testExecuteAjaxWithInvalidEntityIdReturnsJson(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(true);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 0 : null;
        });

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())->method('setData')->with([
            'success' => false,
            'message' => (string) __('Invalid shipment entity ID.'),
        ])->willReturnSelf();
        $this->jsonFactoryStub->method('create')->willReturn($jsonMock);

        $this->assertSame($jsonMock, $this->controller->execute());
    }

    public function testExecuteSuccessStreamsFile(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(false);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 10 : null;
        });

        $labelData = [
            'content' => '%PDF-1.4 dummy return QR code',
            'mimeType' => 'application/pdf',
            'filename' => 'dhl_return_qr_RET123456.pdf',
        ];

        $this->labelPrintingServiceStub->method('getReturnQrCodeContent')->willReturn($labelData);

        $responseStub = $this->createStub(ResponseInterface::class);
        $this->fileFactoryStub->method('create')->willReturn($responseStub);

        $result = $this->controller->execute();
        $this->assertSame($responseStub, $result);
    }

    public function testExecuteServiceExceptionRedirectsToEdit(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(false);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 10 : null;
        });
        $this->labelPrintingServiceStub->method('getReturnQrCodeContent')
            ->willThrowException(new Exception('Print return QR error'));

        $calledPath = null;
        $calledParams = null;
        $this->redirectStub->method('setPath')->willReturnCallback(
            function (string $path, array $params = []) use (&$calledPath, &$calledParams) {
                $calledPath = $path;
                $calledParams = $params;
                return $this->redirectStub;
            }
        );

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
        $this->assertEquals('*/*/edit', $calledPath);
        $this->assertEquals(['entity_id' => 10], $calledParams);
    }

    public function testExecuteAjaxServiceExceptionReturnsJson(): void
    {
        $this->requestStub->method('isXmlHttpRequest')->willReturn(true);
        $this->requestStub->method('getParam')->willReturnCallback(function (string $param) {
            return $param === 'entity_id' ? 10 : null;
        });
        $this->labelPrintingServiceStub->method('getReturnQrCodeContent')
            ->willThrowException(new Exception('Return QR not found'));

        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())->method('setData')->with($this->callback(function (array $data): bool {
            return $data['success'] === false
                && str_contains($data['message'], 'Return QR not found');
        }))->willReturnSelf();
        $this->jsonFactoryStub->method('create')->willReturn($jsonMock);

        $result = $this->controller->execute();
        $this->assertSame($jsonMock, $result);
    }
}
