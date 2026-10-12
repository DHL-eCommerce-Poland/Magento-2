<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BookCourierShipment extends Generic implements ButtonProviderInterface
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
     * Get button data.
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

        // Hide Order Courier button if courier has already been ordered for this shipment.
        if (!empty($entity->getDispatchNumber())) {
            return [];
        }

        $shipmentNumber = trim((string) ($entity->getShipmentId() ?? ''));
        $safeShipmentNumbers = $shipmentNumber !== ''
            ? "['" . $this->escapeJs($shipmentNumber) . "']"
            : '[]';
        $safeLoadingMsg = $this->escapeJs((string) __('Courier booking dialog is loading. Please try again.'));

        return [
            'label' => __('Order Courier'),
            'class' => '',
            'on_click' => sprintf(
                "require(['jquery'], function($) {
                    var modal = $('#dhlpoland-book-courier-modal');
                    if (modal.length && typeof modal.data('openBookCourierModal') === 'function') {
                        modal.data('openBookCourierModal')([%d], %s);
                    } else if (window.DhlPolandBookCourier
                        && typeof window.DhlPolandBookCourier.openModal === 'function'
                    ) {
                        window.DhlPolandBookCourier.openModal([%d], %s);
                    } else {
                        alert('%s');
                    }
                });",
                $entityId,
                $safeShipmentNumbers,
                $entityId,
                $safeShipmentNumbers,
                $safeLoadingMsg
            ),
            'sort_order' => 30,
        ];
    }
}
