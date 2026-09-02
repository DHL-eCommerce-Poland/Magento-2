<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class RecreateShipment extends Generic implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository
    ) {
        parent::__construct($context);
    }

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

        try {
            $entity = $this->shipmentDataRepository->getById($entityId);

            // Hide Save & Resend button if courier has already been ordered for this shipment
            if (!empty($entity->getDispatchNumber())) {
                return [];
            }
        } catch (Throwable $e) {
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
                                var formComponent = registry.get('dhlparcel_shipment_form.dhlparcel_shipment_form');
                                if (formComponent && typeof formComponent.save === 'function') {
                                    formComponent.save();
                                } else {
                                    var \$form = $('form').filter(function() {
                                        return $(this).attr('action') && $(this).attr('action').indexOf('dhlparcel/shipment/save') !== -1;
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
            'sort_order' => 70,
        ];
    }
}
