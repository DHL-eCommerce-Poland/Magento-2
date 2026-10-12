<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Controller\Adminhtml\Help;

use DhlPoland\Dhl\Controller\Adminhtml\Help\Index;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page as BackendPage;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    public function testExecuteReturnsPageResult(): void
    {
        $contextMock = $this->createStub(Context::class);
        $pageFactoryMock = $this->createStub(PageFactory::class);
        $pageMock = $this->createStub(BackendPage::class);
        $configMock = $this->createStub(Config::class);
        $titleMock = $this->createStub(Title::class);

        $pageMock->method('getConfig')->willReturn($configMock);
        $configMock->method('getTitle')->willReturn($titleMock);
        $pageMock->method('setActiveMenu')->willReturnSelf();
        $pageFactoryMock->method('create')->willReturn($pageMock);

        $controller = new Index($contextMock, $pageFactoryMock);
        $result = $controller->execute();

        $this->assertSame($pageMock, $result);
    }
}
