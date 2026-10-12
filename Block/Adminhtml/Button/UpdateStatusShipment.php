<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Button;

use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class UpdateStatusShipment extends Generic implements ButtonProviderInterface
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param AdminhtmlShipmentData $shipmentData
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        private readonly AdminhtmlShipmentData $shipmentData,
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
        $entityId = $this->shipmentData->getEntityId();
        $entity = $this->shipmentData->getShipment();
        if ($entityId === null || $entity === null) {
            return [];
        }

        // Display the button only if shipment has a generated DHL shipment ID.
        if (empty($entity->getShipmentId())) {
            return [];
        }

        $postData = $this->serializer->serialize(
            [
                'action' => $this->getUrl(
                    'dhlpoland/shipment/updateStatus',
                    ['entity_id' => $entityId]
                ),
                'data' => ['redirect_to_edit' => 1],
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
