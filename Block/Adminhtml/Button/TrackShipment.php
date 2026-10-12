<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class TrackShipment extends Generic implements ButtonProviderInterface
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
     * Get button configuration for top action bar.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $shipment = $this->shipmentData->getShipment();
        if ($shipment === null) {
            return [];
        }

        $shipmentId = $shipment->getShipmentId();
        if (empty($shipmentId)) {
            return [];
        }

        return [
            'label' => __('Track Shipment'),
            'class' => '',
            'on_click' => sprintf(
                "window.open('https://mojdhl.pl/sprawdz?paczki=%s', '_blank');",
                $this->escapeJs(urlencode($shipmentId))
            ),
            'sort_order' => 40,
        ];
    }
}
