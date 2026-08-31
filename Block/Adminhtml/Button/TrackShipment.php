<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class TrackShipment implements ButtonProviderInterface
{
    /**
     * @param RequestInterface $request
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository
    ) {
    }

    /**
     * Get button configuration for top action bar.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $entityId = (int) $this->request->getParam('entity_id');
        if (!$entityId) {
            return [];
        }

        try {
            $entity = $this->shipmentDataRepository->getById($entityId);
            $shipmentId = $entity->getShipmentId();
            if (empty($shipmentId)) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        return [
            'label' => __('Track Shipment'),
            'class' => 'action-secondary',
            'on_click' => sprintf(
                "window.open('https://mojdhl.pl/sprawdz?paczki=%s', '_blank');",
                urlencode($shipmentId)
            ),
            'sort_order' => 15,
        ];
    }
}
