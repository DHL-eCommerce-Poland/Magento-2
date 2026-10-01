<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DownloadEpod extends Generic implements ButtonProviderInterface
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
     * Get button configuration for top action bar on shipment edit page.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $entityId = $this->shipmentData->getEntityId();
        $shipment = $this->shipmentData->getShipment();
        if ($entityId === null || $shipment === null || empty($shipment->getShipmentId())) {
            return [];
        }

        $downloadUrl = $this->getUrl('dhlpoland/shipment/downloadEpod', ['entity_id' => $entityId]);

        return [
            'label' => __('Download ePOD'),
            'class' => '',
            'on_click' => sprintf("window.open('%s', '_blank');", $this->escapeJs($downloadUrl)),
            'sort_order' => 54,
        ];
    }
}
