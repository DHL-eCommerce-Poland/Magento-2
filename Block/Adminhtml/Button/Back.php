<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class Back extends Generic implements ButtonProviderInterface
{
    /**
     * Get button data.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'class' => 'back',
            'on_click' => sprintf("location.href = '%s';", $this->escapeJs($this->getUrl('*/*/index'))),
            'sort_order' => 10,
        ];
    }
}
