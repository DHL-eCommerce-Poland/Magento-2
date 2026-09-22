<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Block\Adminhtml\Button\SaveAndContinue;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use PHPUnit\Framework\TestCase;

class SaveAndContinueTest extends TestCase
{
    public function testGetButtonDataReturnsExpectedArray(): void
    {
        $contextStub = $this->createStub(Context::class);
        $escaperStub = $this->createStub(Escaper::class);

        $button = new SaveAndContinue($contextStub, $escaperStub);
        $data = $button->getButtonData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('label', $data);
        $this->assertSame('Save and Continue Edit', (string)$data['label']);
        $this->assertSame('save', $data['class']);
        $this->assertSame('', $data['on_click']);
        $this->assertSame(80, $data['sort_order']);
        $this->assertArrayHasKey('data_attribute', $data);
        $this->assertSame('saveAndContinue', $data['data_attribute']['form-role']);
        $this->assertSame(
            ['button' => ['event' => 'saveAndContinue']],
            $data['data_attribute']['mage-init']
        );
    }
}
