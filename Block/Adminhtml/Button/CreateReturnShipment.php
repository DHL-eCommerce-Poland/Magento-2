<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
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

        $confirmMessage = $this->escapeJs(
            (string) __('Are you sure you want to create a return shipment for this parcel?')
        );
        $url = $this->escapeJs($this->getUrl(
            'dhlparcel/shipment/createReturnShipment',
            ['entity_id' => $entityId]
        ));

        return [
            'label' => __('Create Return Shipment'),
            'class' => '',
            'on_click' => sprintf(
                "confirmSetLocation('%s', '%s')",
                $confirmMessage,
                $url
            ),
            'sort_order' => 65,
        ];
    }
}
