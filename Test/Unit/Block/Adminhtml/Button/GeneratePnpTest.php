<?php

declare(strict_types=1);

namespace Ideo\Dhl\Test\Unit\Block\Adminhtml\Button;

use Ideo\Dhl\Block\Adminhtml\Button\GeneratePnp;
use Magento\Backend\Block\Widget\Context;
use PHPUnit\Framework\TestCase;

class GeneratePnpTest extends TestCase
{
    public function testGetButtonDataReturnsExpectedArray(): void
    {
        $contextStub = $this->createStub(Context::class);
        $button = new GeneratePnp($contextStub);
        $data = $button->getButtonData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('label', $data);
        $this->assertArrayHasKey('class', $data);
        $this->assertArrayHasKey('on_click', $data);
        $this->assertEquals('action-secondary', $data['class']);
        $this->assertStringContainsString('ideo-dhl-pnp-modal', $data['on_click']);
    }
}
