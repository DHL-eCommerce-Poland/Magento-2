<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class BookCourierShipment extends Generic implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository
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
        $entityId = $this->getEntityId();
        if (!$entityId) {
            return [];
        }

        try {
            $entity = $this->shipmentDataRepository->getById($entityId);

            // Hide Order Courier button if courier has already been ordered for this shipment
            if (!empty($entity->getDispatchNumber())) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $shipmentNumber = $entity->getShipmentId() ?: (string) $entityId;

        $safeShipmentNumber = $this->escaper->escapeJs($shipmentNumber);
        $safeLoadingMsg = $this->escaper->escapeJs((string) __('Courier booking dialog is loading. Please try again.'));

        return [
            'label' => __('Order Courier'),
            'class' => '',
            'on_click' => sprintf(
                "require(['jquery'], function($) {
                    var modal = $('#ideo-dhl-book-courier-modal');
                    if (modal.length && typeof modal.data('openBookCourierModal') === 'function') {
                        modal.data('openBookCourierModal')([%d], ['%s']);
                    } else if (window.IdeoDhlBookCourier && typeof window.IdeoDhlBookCourier.openModal === 'function') {
                        window.IdeoDhlBookCourier.openModal([%d], ['%s']);
                    } else {
                        alert('%s');
                    }
                });",
                $entityId,
                $safeShipmentNumber,
                $entityId,
                $safeShipmentNumber,
                $safeLoadingMsg
            ),
            'sort_order' => 30,
        ];
    }
}
