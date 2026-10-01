<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class CancelCourierShipment extends Generic implements ButtonProviderInterface
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
     * Get button data for shipment edit form header.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $entityId = $this->shipmentData->getEntityId();
        $entity = $this->shipmentData->getShipment();
        if ($entityId === null || $entity === null) {
            return [];
        }

        // Display the button only if shipment has an active courier dispatch number.
        if (empty($entity->getDispatchNumber())) {
            return [];
        }

        $confirmMessage = $this->escapeJs(
            (string) __('Are you sure you want to cancel courier booking for this shipment?')
        );
        $url = $this->escapeJs($this->getUrl(
            'dhlpoland/shipment/cancelCourierBooking',
            ['entity_id' => $entityId]
        ));

        return [
            'label' => __('Cancel Courier Booking'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                $confirmMessage,
                $url
            ),
            'sort_order' => 30,
        ];
    }
}
