<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteShipment extends Generic implements ButtonProviderInterface
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
     * Get button data for deleting DHL shipment.
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

        // Hide Delete button if courier has already been ordered for this shipment.
        if (!empty($entity->getDispatchNumber())) {
            return [];
        }

        $confirmMessage = $this->escapeJs((string) __(
            'Are you sure you want to delete this shipment?'
            . ' It will be cancelled in DHL and removed from Magento.'
        ));
        $url = $this->escapeJs($this->getUrl('dhlpoland/shipment/delete', ['entity_id' => $entityId]));

        return [
            'label' => __('Delete Shipment'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                $confirmMessage,
                $url
            ),
            'sort_order' => 20,
        ];
    }
}
