<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class DeleteShipment extends Generic implements ButtonProviderInterface
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

        try {
            $entity = $this->shipmentDataRepository->getById($entityId);

            // Hide Delete button if courier has already been ordered for this shipment
            if (!empty($entity->getDispatchNumber())) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        return [
            'label' => __('Delete Shipment'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __(
                    'Are you sure you want to delete this shipment?'
                    . ' It will be cancelled in DHL and removed from Magento.'
                ),
                $this->getUrl('dhlparcel/shipment/delete', ['entity_id' => $entityId])
            ),
            'sort_order' => 20,
        ];
    }
}
