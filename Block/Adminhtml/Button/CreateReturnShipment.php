<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class CreateReturnShipment extends Generic implements ButtonProviderInterface
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
     * Get button data for shipment edit form header.
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

            // Display button ONLY if shipment has a generated DHL shipment ID and NO return shipment yet
            if (empty($entity->getShipmentId()) || !empty($entity->getReturnShipmentId())) {
                return [];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [
            'label' => __('Create Return Shipment'),
            'class' => 'action-secondary',
            'on_click' => sprintf(
                "confirmSetLocation('%s', '%s')",
                __('Are you sure you want to create a return shipment for this parcel?'),
                $this->getUrl('ideo_dhl/shipment/createReturnShipment', ['entity_id' => $entityId])
            ),
            'sort_order' => 65,
        ];
    }
}
