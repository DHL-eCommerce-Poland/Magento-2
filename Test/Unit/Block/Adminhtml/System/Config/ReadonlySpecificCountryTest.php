<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\System\Config;

use Ideo\Dhl\Block\Adminhtml\System\Config\ReadonlySpecificCountry;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use PHPUnit\Framework\TestCase;

class ReadonlySpecificCountryTest extends TestCase
{
    /**
     * @var ReadonlySpecificCountry
     */
    private $block;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $secureRenderer = $this->createStub(SecureHtmlRenderer::class);

        $omStub = $this->createStub(ObjectManager::class);
        $omStub->method('get')->willReturn($secureRenderer);
        ObjectManager::setInstance($omStub);

        $this->block = new ReadonlySpecificCountry($context, [], $secureRenderer);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(ObjectManager::class);
        if ($reflection->hasProperty('_instance')) {
            $prop = $reflection->getProperty('_instance');
            $prop->setValue(null, null);
        }
    }

    public function testRenderSetsDisabledAndReadonlyAndDisablesInheritance(): void
    {
        $element = new class extends AbstractElement {
            public function __construct()
            {
                // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
            }

            public function getElements(): array
            {
                return [];
            }

            public function getHtmlId(): string
            {
                return (string)$this->getData('html_id');
            }

            public function getElementHtml(): string
            {
                return '<select id="' . $this->getHtmlId() . '"></select>';
            }
        };

        $element->setHtmlId('test_specificcountry_id');
        $element->setLabel('Ship to Specific Countries');

        $html = $this->block->render($element);

        $this->assertTrue((bool)$element->getDisabled());
        $this->assertTrue((bool)$element->getData('readonly'));
        $this->assertTrue((bool)$element->getIsDisableInheritance());
        $this->assertFalse((bool)$element->getData('can_be_empty'));
        $this->assertStringContainsString('test_specificcountry_id', $html);
    }

    public function testGetElementHtmlSetsDisabledAndReadonlyAndStripsHiddenInputsAndName(): void
    {
        $element = new class extends AbstractElement {
            public function __construct()
            {
                // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
            }

            public function getElements(): array
            {
                return [];
            }

            public function getElementHtml(): string
            {
                return '<input type="hidden" name="dummy" value="" />' .
                    '<select name="groups[connect][fields][specificcountry][value][]"></select>';
            }
        };

        $reflection = new \ReflectionClass($this->block);
        $method = $reflection->getMethod('_getElementHtml');

        $html = $method->invoke($this->block, $element);

        $this->assertTrue((bool)$element->getDisabled());
        $this->assertTrue((bool)$element->getData('readonly'));
        $this->assertFalse((bool)$element->getData('can_be_empty'));
        $this->assertStringNotContainsString('type="hidden"', $html);
        $this->assertStringNotContainsString('name=', $html);
        $this->assertSame('<select></select>', $html);
    }
}
