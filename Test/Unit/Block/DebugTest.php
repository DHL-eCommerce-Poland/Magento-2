<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Test\Unit\Block;

use DhlPoland\Dhl\Block\Debug;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase
{
    public function testIsDebugReturnsTrueWhenEnabled(): void
    {
        $context = $this->createStub(Context::class);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('carriers/dhlpoland/debug', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $block = new Debug($context, $scopeConfig);
        $this->assertTrue($block->isDebug());
    }

    public function testIsDebugReturnsFalseWhenDisabled(): void
    {
        $context = $this->createStub(Context::class);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('carriers/dhlpoland/debug', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $block = new Debug($context, $scopeConfig);
        $this->assertFalse($block->isDebug());
    }
}
