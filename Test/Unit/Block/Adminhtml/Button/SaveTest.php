<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Block\Adminhtml\Button\Save;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    public function testGetButtonDataReturnsExpectedArray(): void
    {
        $contextStub = $this->createStub(Context::class);
        $escaperStub = $this->createStub(Escaper::class);

        $button = new Save($contextStub, $escaperStub);
        $data = $button->getButtonData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('label', $data);
        $this->assertSame('Save', (string)$data['label']);
        $this->assertSame('save primary', $data['class']);
        $this->assertSame('', $data['on_click']);
        $this->assertSame(90, $data['sort_order']);
        $this->assertArrayHasKey('data_attribute', $data);
        $this->assertSame('save', $data['data_attribute']['form-role']);
        $this->assertSame(
            ['button' => ['event' => 'save']],
            $data['data_attribute']['mage-init']
        );
    }
}
