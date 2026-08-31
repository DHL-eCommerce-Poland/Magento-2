<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class UpdateStatusShipment extends Generic implements ButtonProviderInterface
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

            // Display button ONLY if shipment has a generated DHL shipment ID
            if (empty($entity->getShipmentId())) {
                return [];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [
            'label' => __('Update Status'),
            'class' => 'action-secondary',
            'on_click' => sprintf(
                "location.href = '%s'",
                $this->getUrl('ideo_dhl/shipment/updateStatus', ['entity_id' => $entityId, 'redirect_to_edit' => 1])
            ),
            'sort_order' => 60,
        ];
    }
}
