<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class GeneratePnp extends Generic implements ButtonProviderInterface
{
    /**
     * Get button data for generating PNP report.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $loadingMsg = $this->escaper->escapeJs((string) __('PNP report dialog is loading. Please try again.'));

        return [
            'label' => __('Generate PNP Report'),
            'class' => 'action-secondary',
            'on_click' => "require(['jquery'], function($) {
                var modal = $('#dhlpoland-pnp-modal');
                if (modal.length && modal.data('mageModal')) {
                    modal.modal('openModal');
                } else if (window.DhlPolandPnp && typeof window.DhlPolandPnp.openModal === 'function') {
                    window.DhlPolandPnp.openModal();
                } else {
                    alert('" . $loadingMsg . "');
                }
            });",
            'sort_order' => 10,
        ];
    }
}
