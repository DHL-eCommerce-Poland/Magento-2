<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class CancelCourierShipment extends Generic implements ButtonProviderInterface
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

            // Display button ONLY if shipment has an active courier dispatch number
            if (empty($entity->getDispatchNumber())) {
                return [];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [
            'label' => __('Cancel Courier Booking'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to cancel courier booking for this shipment?'),
                $this->getUrl('ideo_dhl/shipment/cancelCourierBooking', ['entity_id' => $entityId])
            ),
            'sort_order' => 30,
        ];
    }
}
