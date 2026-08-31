<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Controller\Adminhtml\Shipment\MassDownloadScan;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\Collection;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Ideo\Dhl\Model\Shipment\DocumentDownloadService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class MassDownloadScanTest extends TestCase
{
    private Context|Stub $contextStub;
    private RequestInterface|Stub $requestStub;
    private RedirectFactory|Stub $redirectFactoryStub;
    private Redirect|Stub $redirectStub;
    private Filter|Stub $filterStub;
    private CollectionFactory|Stub $collectionFactoryStub;
    private Collection|Stub $collectionStub;
    private DocumentDownloadService|Stub $serviceStub;
    private FileFactory|Stub $fileFactoryStub;
    private ManagerInterface|Stub $messageManagerStub;
    private MassDownloadScan $controller;

    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestStub = $this->createStub(RequestInterface::class);
        $this->redirectFactoryStub = $this->createStub(RedirectFactory::class);
        $this->redirectStub = $this->createStub(Redirect::class);
        $this->filterStub = $this->createStub(Filter::class);
        $this->collectionFactoryStub = $this->createStub(CollectionFactory::class);
        $this->collectionStub = $this->createStub(Collection::class);
        $this->serviceStub = $this->createStub(DocumentDownloadService::class);
        $this->fileFactoryStub = $this->createStub(FileFactory::class);
        $this->messageManagerStub = $this->createStub(ManagerInterface::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestStub);
        $this->contextStub->method('getResultRedirectFactory')->willReturn($this->redirectFactoryStub);
        $this->contextStub->method('getMessageManager')->willReturn($this->messageManagerStub);
        $this->redirectFactoryStub->method('create')->willReturn($this->redirectStub);
        $this->collectionFactoryStub->method('create')->willReturn($this->collectionStub);
        $this->filterStub->method('getCollection')->willReturn($this->collectionStub);

        $this->controller = new MassDownloadScan(
            $this->contextStub,
            $this->filterStub,
            $this->collectionFactoryStub,
            $this->serviceStub,
            $this->fileFactoryStub
        );
    }

    public function testExecuteSuccessStreamsFile(): void
    {
        $item1 = new \Magento\Framework\DataObject(['entity_id' => 1]);
        $item2 = new \Magento\Framework\DataObject(['entity_id' => 2]);
        $this->collectionStub->method('getIterator')->willReturn(new \ArrayIterator([$item1, $item2]));

        $zipData = [
            'content' => 'zip_binary_data',
            'mimeType' => 'application/zip',
            'filename' => 'dhl_scans_20260831.zip',
        ];

        $this->serviceStub->method('getBulkScanContent')->willReturn($zipData);

        $responseStub = $this->createStub(ResponseInterface::class);
        $this->fileFactoryStub->method('create')->willReturn($responseStub);

        $result = $this->controller->execute();
        $this->assertSame($responseStub, $result);
    }

    public function testExecuteServiceExceptionRedirectsWithError(): void
    {
        $item1 = new \Magento\Framework\DataObject(['entity_id' => 1]);
        $this->collectionStub->method('getIterator')->willReturn(new \ArrayIterator([$item1]));

        $this->serviceStub->method('getBulkScanContent')->willThrowException(new Exception('Error fetching scans'));
        $this->redirectStub->method('setPath')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }
}
