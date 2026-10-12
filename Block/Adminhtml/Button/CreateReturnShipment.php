<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class CreateReturnShipment extends Generic implements ButtonProviderInterface
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
     * Get button data for shipment edit form header.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $entityId = $this->shipmentData->getEntityId();
        $entity = $this->shipmentData->getShipment();
        if ($entityId === null || $entity === null) {
            return [];
        }

        // Display the button only if a DHL shipment exists and no return shipment was created.
        if (empty($entity->getShipmentId()) || !empty($entity->getReturnShipmentId())) {
            return [];
        }

        $url = $this->getUrl(
            'dhlpoland/shipment/createReturnShipment',
            ['entity_id' => $entityId]
        );

        return [
            'label' => __('Create Return Shipment'),
            'class' => '',
            'on_click' => sprintf(
                "require(['DhlPoland_Dhl/js/shipment/create-return'], function (createReturn) { createReturn('%s'); });",
                $this->escapeJs($url)
            ),
            'sort_order' => 65,
        ];
    }
}
