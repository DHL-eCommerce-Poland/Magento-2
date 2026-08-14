<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\System\Config;

use Ideo\Dhl\Block\Adminhtml\System\Config\Export;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportTest extends TestCase
{
    /**
     * @var LayoutInterface|MockObject
     */
    private LayoutInterface|MockObject $layoutMock;

    /**
     * @var UrlInterface|MockObject
     */
    private UrlInterface|MockObject $urlBuilderMock;

    /**
     * @var Export
     */
    private Export $exportBlock;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $this->layoutMock = $this->createMock(LayoutInterface::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);

        $context->method('getLayout')->willReturn($this->layoutMock);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilderMock);

        $omStub = $this->createStub(ObjectManager::class);
        ObjectManager::setInstance($omStub);

        $this->exportBlock = new Export($context);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(ObjectManager::class);
        if ($reflection->hasProperty('_instance')) {
            $prop = $reflection->getProperty('_instance');
            $prop->setValue(null, null);
        }
    }

    public function testRendersExportButtonHtml(): void
    {
        $this->urlBuilderMock
            ->expects($this->once())
            ->method('getUrl')
            ->with('ideo_dhl/system_config/exportTablerates')
            ->willReturn('http://example.com/admin/ideo_dhl/system_config/exportTablerates');

        $buttonMock = $this->createMock(Button::class);
        $buttonMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(static function (array $data) {
                return $data['id'] === 'export_dhl_tablerates_button'
                    && str_contains($data['onclick'], 'exportTablerates');
            }))
            ->willReturnSelf();

        $buttonMock->expects($this->once())
            ->method('toHtml')
            ->willReturn('<button type="button">Export CSV</button>');

        $this->layoutMock
            ->expects($this->once())
            ->method('createBlock')
            ->with(Button::class)
            ->willReturn($buttonMock);

        $elementStub = $this->createStub(AbstractElement::class);
        $html = $this->exportBlock->render($elementStub);

        $this->assertStringContainsString('Export CSV', $html);
    }
}
