<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Shipment\Edit;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\SerializerInterface;
use Throwable;

class TrackingHistory extends Template
{
    /**
     * @param Context $context
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param SerializerInterface $serializer
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly SerializerInterface $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get tracking events array for current shipment entity.
     *
     * @return array
     */
    public function getTrackingEvents(): array
    {
        $entityId = (int) $this->getRequest()->getParam('entity_id');
        if (!$entityId) {
            return [];
        }

        try {
            $shipment = $this->shipmentDataRepository->getById($entityId);
            $rawPayload = $shipment->getShipmentData();
            if (empty($rawPayload) || !is_string($rawPayload)) {
                return [];
            }

            $payload = $this->serializer->unserialize($rawPayload);
            if (is_array($payload) && isset($payload['trackingEvents']) && is_array($payload['trackingEvents'])) {
                return $payload['trackingEvents'];
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    /**
     * Get DHL shipment ID / package number.
     *
     * @return string
     */
    public function getShipmentId(): string
    {
        $entityId = (int) $this->getRequest()->getParam('entity_id');
        if (!$entityId) {
            return '';
        }

        try {
            $shipment = $this->shipmentDataRepository->getById($entityId);
            return (string) ($shipment->getShipmentId() ?? '');
        } catch (Throwable) {
            return '';
        }
    }
}
