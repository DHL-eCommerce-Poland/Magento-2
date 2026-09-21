<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\DeliveryService;

use Ideo\Dhl\Api\Data\DeliveryServiceInterfaceFactory;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Controller\Adminhtml\DeliveryService\Save;
use Ideo\Dhl\Model\DeliveryService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    /**
     * @var Context&MockObject
     */
    private Context $context;

    /**
     * @var DeliveryServiceRepositoryInterface&MockObject
     */
    private DeliveryServiceRepositoryInterface $deliveryServiceRepository;

    /**
     * @var DeliveryServiceInterfaceFactory&MockObject
     */
    private DeliveryServiceInterfaceFactory $deliveryServiceFactory;

    /**
     * @var HttpRequest&MockObject
     */
    private HttpRequest $request;

    /**
     * @var RedirectFactory&MockObject
     */
    private RedirectFactory $resultRedirectFactory;

    /**
     * @var Redirect&MockObject
     */
    private Redirect $resultRedirect;

    /**
     * @var ManagerInterface&MockObject
     */
    private ManagerInterface $messageManager;

    /**
     * @var Save
     */
    private Save $controller;

    protected function setUp(): void
    {
        $this->context = $this->createStub(Context::class);
        $this->deliveryServiceRepository = $this->createMock(DeliveryServiceRepositoryInterface::class);
        $this->deliveryServiceFactory = $this->createStub(DeliveryServiceInterfaceFactory::class);
        $this->request = $this->createStub(HttpRequest::class);
        $this->resultRedirectFactory = $this->createStub(RedirectFactory::class);
        $this->resultRedirect = $this->createMock(Redirect::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);

        $this->context->method('getRequest')->willReturn($this->request);
        $this->context->method('getResultRedirectFactory')->willReturn($this->resultRedirectFactory);
        $this->context->method('getMessageManager')->willReturn($this->messageManager);
        $this->resultRedirectFactory->method('create')->willReturn($this->resultRedirect);

        $this->controller = new Save(
            $this->context,
            $this->deliveryServiceRepository,
            $this->deliveryServiceFactory
        );
    }

    public function testExecuteSetsPackagesToNullWhenOmittedFromPost(): void
    {
        $postData = [
            'entity_id' => '1',
            'template_name' => 'Standard',
            // packages is omitted (all rows deleted)
        ];

        $this->request->method('getPostValue')->willReturn($postData);
        $this->request->method('getParam')->willReturnMap([
            ['entity_id', null, '1'],
            ['back', null, null],
        ]);

        $model = $this->createMock(DeliveryService::class);
        $this->deliveryServiceRepository->expects($this->once())
            ->method('getById')
            ->with(1)
            ->willReturn($model);

        $model->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data) {
                return array_key_exists('packages', $data) && $data['packages'] === null;
            }));

        $this->deliveryServiceRepository->expects($this->once())
            ->method('save')
            ->with($model);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage');

        $result = $this->controller->execute();
        $this->assertSame($this->resultRedirect, $result);
    }
}
