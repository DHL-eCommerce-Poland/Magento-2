<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class RecreateShipment extends Generic implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository
    ) {
        parent::__construct($context, $escaper);
    }

    /**
     * Get button data.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $entityId = $this->getShipmentEntityId();
        if ($entityId <= 0) {
            return [];
        }

        try {
            $entity = $this->shipmentDataRepository->getById($entityId);

            // Hide Save & Resend button if courier has already been ordered for this shipment
            if (!empty($entity->getDispatchNumber())) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $title = __('Confirm Shipment Re-creation');
        $msg = __(
            'Clicking "Save & Resend to DHL" will cancel the existing DHL shipment'
            . ' and generate a new shipment for the updated data.'
        );
        $safeTitle = $this->escaper->escapeJs((string) $title);
        $safeMsg = $this->escaper->escapeJs((string) $msg);

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
                                var formComponent = registry.get('dhlparcel_shipment_form.dhlparcel_shipment_form');
                                if (formComponent && typeof formComponent.save === 'function') {
                                    formComponent.save();
                                } else {
                                    var \$form = $('form').filter(function() {
                                        var act = $(this).attr('action');
                                        return act && act.indexOf('dhlparcel/shipment/save') !== -1;
                                    });
                                    if (\$form.length) {
                                        \$form.trigger('submit');
                                    }
                                }
                            }
                        }
                    });
                });",
                $safeTitle,
                $safeMsg
            ),
            'sort_order' => 70,
        ];
    }
}
