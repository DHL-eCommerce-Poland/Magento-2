<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class GeneratePnp extends Generic implements ButtonProviderInterface
{
    /**
     * Get button data for generating courier report (PnP).
     *
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Generate Courier Report (PnP)'),
            'class' => 'action-secondary',
            'on_click' => "require(['jquery'], function($) {
                var modal = $('#ideo-dhl-pnp-modal');
                if (modal.length && modal.data('mageModal')) {
                    modal.modal('openModal');
                } else if (window.IdeoDhlPnp && typeof window.IdeoDhlPnp.openModal === 'function') {
                    window.IdeoDhlPnp.openModal();
                } else {
                    alert('" . addslashes((string) __('Courier report dialog is loading. Please try again.')) . "');
                }
            });",
            'sort_order' => 10,
        ];
    }
}
