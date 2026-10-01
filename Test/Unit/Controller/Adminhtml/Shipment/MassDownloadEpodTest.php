<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Controller\Adminhtml\Shipment\MassDownloadEpod;
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

class MassDownloadEpodTest extends TestCase
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
    private $filterStub;
    /**
     * @var Stub
     */
    private $collectionFactoryStub;
    /**
     * @var Stub
     */
    private $collectionStub;
    /**
     * @var Stub
     */
    private $serviceStub;
    /**
     * @var Stub
     */
    private $fileFactoryStub;
    /**
     * @var Stub
     */
    private $messageManagerStub;
    /**
     * @var MassDownloadEpod
     */
    private $controller;

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

        $this->controller = new MassDownloadEpod(
            $this->contextStub,
            $this->filterStub,
            $this->collectionFactoryStub,
            $this->serviceStub,
            $this->fileFactoryStub,
            $this->createStub(\Psr\Log\LoggerInterface::class)
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
            'filename' => 'dhl_epods_20260831.zip',
        ];

        $this->serviceStub->method('getBulkEpodContent')->willReturn($zipData);

        $responseStub = $this->createStub(ResponseInterface::class);
        $this->fileFactoryStub->method('create')->willReturn($responseStub);

        $result = $this->controller->execute();
        $this->assertSame($responseStub, $result);
    }

    public function testExecuteServiceExceptionRedirectsWithError(): void
    {
        $item1 = new \Magento\Framework\DataObject(['entity_id' => 1]);
        $this->collectionStub->method('getIterator')->willReturn(new \ArrayIterator([$item1]));

        $this->serviceStub->method('getBulkEpodContent')->willThrowException(new Exception('Error fetching ePODs'));
        $this->redirectStub->method('setPath')->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->redirectStub, $result);
    }
}
