<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteShipment extends Generic implements ButtonProviderInterface
{
    /**
     * Get button data for deleting DHL shipment.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $entityId = $this->getEntityId();
        if (!$entityId) {
            return [];
        }

        return [
            'label' => __('Delete Shipment'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this shipment? It will be cancelled in DHL and removed from Magento.'),
                $this->getUrl('ideo_dhl/shipment/delete', ['entity_id' => $entityId])
            ),
            'sort_order' => 20,
        ];
    }
}
