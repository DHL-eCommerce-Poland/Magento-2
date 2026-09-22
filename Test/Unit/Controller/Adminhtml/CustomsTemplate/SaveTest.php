<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Controller\Adminhtml\CustomsTemplate;

use Ideo\Dhl\Controller\Adminhtml\CustomsTemplate\Save;
use Ideo\Dhl\Model\CustomsTemplate;
use Ideo\Dhl\Model\CustomsTemplateFactory;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Ideo\Dhl\Model\Source\CustomsCurrency;
use Ideo\Dhl\Model\Source\CustomsType;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Locale\ListsInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    private Context $context;
    private CustomsTemplateRepository&MockObject $repository;
    private CustomsTemplateFactory&MockObject $templateFactory;
    private SerializerInterface $serializer;
    private CustomsCurrency $customsCurrency;
    private HttpRequest $request;
    private RedirectFactory $resultRedirectFactory;
    private Redirect&MockObject $resultRedirect;
    private ManagerInterface&MockObject $messageManager;
    private Save $controller;

    protected function setUp(): void
    {
        $this->context = $this->createStub(Context::class);
        $this->repository = $this->createMock(CustomsTemplateRepository::class);
        $this->templateFactory = $this->createMock(CustomsTemplateFactory::class);
        $this->serializer = $this->createStub(SerializerInterface::class);
        $localeLists = $this->createStub(ListsInterface::class);
        $this->customsCurrency = new CustomsCurrency($localeLists);
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
            $this->repository,
            $this->templateFactory,
            $this->serializer,
            $this->customsCurrency
        );
    }

    public function testExecuteRedirectsToIndexWhenEmptyData(): void
    {
        $this->request->method('getPostValue')->willReturn([]);
        $this->templateFactory->expects($this->never())->method('create');
        $this->repository->expects($this->never())->method('save');
        $this->messageManager->expects($this->never())->method('addSuccessMessage');
        $this->messageManager->expects($this->never())->method('addErrorMessage');
        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->resultRedirect, $result);
    }

    public function testExecuteSuccessWithValidCurrency(): void
    {
        $postData = [
            'template_name' => 'Individual Clearance Template',
            'customs_type' => CustomsType::TYPE_INDIVIDUAL,
            'currency' => 'EUR',
        ];

        $this->request->method('getPostValue')->willReturn($postData);
        $this->request->method('getParam')->willReturnMap([
            ['entity_id', null, 0],
            ['back', null, null],
        ]);

        $template = $this->createMock(CustomsTemplate::class);
        $this->templateFactory->expects($this->once())
            ->method('create')
            ->willReturn($template);

        $template->expects($this->once())
            ->method('addData')
            ->with($postData);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($template);

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage');

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('*/*/index')
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->resultRedirect, $result);
    }

    public function testExecuteFailsWhenCurrencyDisallowedForCustomsType(): void
    {
        $postData = [
            'template_name' => 'Individual Clearance Invalid',
            'customs_type' => CustomsType::TYPE_INDIVIDUAL,
            'currency' => 'PLN', // PLN is not allowed for Individual clearance
        ];

        $this->request->method('getPostValue')->willReturn($postData);
        $this->request->method('getParam')->willReturnMap([
            ['entity_id', null, 0],
            ['back', null, null],
        ]);

        $template = $this->createStub(CustomsTemplate::class);
        $this->templateFactory->expects($this->once())
            ->method('create')
            ->willReturn($template);

        $this->repository->expects($this->never())
            ->method('save');

        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->callback(function ($message) {
                return str_contains((string)$message, 'PLN')
                    && str_contains((string)$message, CustomsType::TYPE_INDIVIDUAL);
            }));

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('*/*/new')
            ->willReturnSelf();

        $result = $this->controller->execute();
        $this->assertSame($this->resultRedirect, $result);
    }
}
