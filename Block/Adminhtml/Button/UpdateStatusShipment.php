<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Throwable;

class UpdateStatusShipment extends Generic implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly SerializerInterface $serializer
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
        $entityId = $this->getEntityId();
        if (!$entityId) {
            return [];
        }

        try {
            $entity = $this->shipmentDataRepository->getById($entityId);

            // Display button ONLY if shipment has a generated DHL shipment ID
            if (empty($entity->getShipmentId())) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $postData = $this->serializer->serialize(
            [
                'action' => $this->getUrl(
                    'dhlparcel/shipment/updateStatus',
                    ['entity_id' => $entityId, 'redirect_to_edit' => 1]
                ),
                'data' => [],
            ]
        );

        return [
            'label' => __('Update Status'),
            'class' => '',
            'on_click' => sprintf(
                "require(['mage/dataPost'], function (dataPost) { dataPost().postData(%s); });",
                $postData
            ),
            'sort_order' => 60,
        ];
    }
}
