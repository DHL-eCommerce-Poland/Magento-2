<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class DownloadEpod implements ButtonProviderInterface
{
    /**
     * @param RequestInterface $request
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Get button configuration for top action bar on shipment edit page.
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

        $downloadUrl = $this->urlBuilder->getUrl('dhlparcel/shipment/downloadEpod', ['entity_id' => $entityId]);

        return [
            'label' => __('Download ePOD'),
            'class' => 'action-secondary',
            'on_click' => sprintf("window.open('%s', '_blank');", $downloadUrl),
            'sort_order' => 54,
        ];
    }
}
