<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class RecreateShipment extends Generic implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param AdminhtmlShipmentData $shipmentData
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        private readonly AdminhtmlShipmentData $shipmentData
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
        $entity = $this->shipmentData->getShipment();
        if ($entity === null) {
            return [];
        }

        // Hide Save & Resend button if courier has already been ordered for this shipment.
        if (!empty($entity->getDispatchNumber())) {
            return [];
        }

        $title = __('Confirm Shipment Re-creation');
        $msg = __(
            'Clicking "Save & Resend to DHL" will cancel the existing DHL shipment'
            . ' and generate a new shipment for the updated data.'
        );
        $safeTitle = $this->escapeJs((string) $title);
        $safeMsg = $this->escapeJs((string) $msg);

        $saveUrl = $this->escapeJs($this->getUrl('dhlpoland/shipment/save', ['entity_id' => $entity->getEntityId()]));

        return [
            'label' => __('Save & Resend to DHL'),
            'class' => 'save primary',
            'on_click' => sprintf(
                "require(['DhlPoland_Dhl/js/shipment/recreate-shipment'], function(recreateShipment) {
                    recreateShipment('%s', '%s', '%s');
                });",
                $safeTitle,
                $safeMsg,
                $saveUrl
            ),
            'sort_order' => 70,
        ];
    }
}
