<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class BookCourierShipment extends Generic implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param mixed $request
     * @param mixed $urlBuilder
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        mixed $request = null,
        mixed $urlBuilder = null
    ) {
        parent::__construct($context);
    }

    /**
     * Get button data for ordering courier for current shipment.
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
        } catch (Throwable $e) {
            return [];
        }

        return [
            'label' => __('Order Courier'),
            'class' => 'action-secondary',
            'on_click' => sprintf(
                "require(['jquery'], function($) {
                    var modal = $('#ideo-dhl-book-courier-modal');
                    if (modal.length && typeof modal.data('openBookCourierModal') === 'function') {
                        modal.data('openBookCourierModal')([%d]);
                    } else if (window.IdeoDhlBookCourier && typeof window.IdeoDhlBookCourier.openModal === 'function') {
                        window.IdeoDhlBookCourier.openModal([%d]);
                    } else {
                        alert('%s');
                    }
                });",
                $entityId,
                $entityId,
                addslashes((string) __('Courier booking dialog is loading. Please try again.'))
            ),
            'sort_order' => 80,
        ];
    }
}
