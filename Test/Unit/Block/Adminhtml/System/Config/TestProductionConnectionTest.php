<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\System\Config;

use Ideo\Dhl\Block\Adminhtml\System\Config\TestProductionConnection;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;

class TestProductionConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(ObjectManager::class);
        if ($reflection->hasProperty('_instance')) {
            $property = $reflection->getProperty('_instance');
            $property->setValue(null, null);
        }
    }

    public function testRendersConnectionMessagesAsTextAndEscapesTheActionUrl(): void
    {
        $context = $this->createStub(Context::class);
        $layout = $this->createMock(LayoutInterface::class);
        $urlBuilder = $this->createMock(UrlInterface::class);
        $context->method('getLayout')->willReturn($layout);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);

        $omStub = $this->createStub(ObjectManager::class);
        ObjectManager::setInstance($omStub);

        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeJs')->willReturnCallback(static fn (string $value): string => addslashes($value));

        $urlBuilder->method('getUrl')
            ->with('dhlparcel/system_config/testConnection')
            ->willReturn("http://example.com/testConnection');alert(1);//");

        $button = $this->createMock(Button::class);
        $button->expects($this->once())
            ->method('setData')
            ->with($this->callback(static function (array $data): bool {
                return $data['id'] === 'dhl_test_connection_production_button'
                    && !str_contains($data['onclick'], "testConnection');alert(1)");
            }))
            ->willReturnSelf();
        $button->method('toHtml')->willReturn('<button>Test</button>');
        $layout->expects($this->once())->method('createBlock')->with(Button::class)->willReturn($button);

        $block = new TestProductionConnection($context, $escaper);
        $html = $block->render($this->createStub(AbstractElement::class));

        $this->assertStringContainsString(".text(response.message || '').html()", $html);
    }
}
