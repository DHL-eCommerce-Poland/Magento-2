<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class RecreateShipment extends Generic implements ButtonProviderInterface
{
    /**
     * Get button data for re-creating/re-dispatching DHL shipment.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $entityId = $this->getEntityId();
        if (!$entityId) {
            return [];
        }

        $msg = __('Clicking "Save & Resend to DHL" will cancel the existing DHL shipment and generate a new shipment for the updated data.');
        $title = __('Confirm Shipment Re-creation');

        return [
            'label' => __('Save & Resend to DHL'),
            'class' => 'save primary',
            'on_click' => sprintf(
                "require(['Magento_Ui/js/modal/confirm', 'uiRegistry', 'jquery'], function(confirm, registry, $) {
                    confirm({
                        title: '%s',
                        content: '%s',
                        actions: {
                            confirm: function() {
                                var formComponent = registry.get('ideo_dhl_shipment_form.ideo_dhl_shipment_form');
                                if (formComponent && typeof formComponent.save === 'function') {
                                    formComponent.save();
                                } else {
                                    var \$form = $('form').filter(function() {
                                        return $(this).attr('action') && $(this).attr('action').indexOf('ideo_dhl/shipment/save') !== -1;
                                    });
                                    if (\$form.length) {
                                        \$form.trigger('submit');
                                    }
                                }
                            }
                        }
                    });
                });",
                addslashes((string) $title),
                addslashes((string) $msg)
            ),
            'sort_order' => 90,
        ];
    }
}
