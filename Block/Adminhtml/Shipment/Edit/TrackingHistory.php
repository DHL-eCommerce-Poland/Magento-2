<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Block\Adminhtml\Shipment\Edit;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Block\Adminhtml\Shipment\AdminhtmlShipmentData;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class TrackingHistory extends Template
{
    /**
     * @param Context $context
     * @param AdminhtmlShipmentData $shipmentData
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly AdminhtmlShipmentData $shipmentData,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get tracking events array for current shipment entity.
     *
     * @return array
     */
    public function getTrackingEvents(): array
    {
        $shipment = $this->shipmentData->getShipment();
        if ($shipment === null) {
            return [];
        }

        $rawPayload = $shipment->getShipmentData();
        if (empty($rawPayload) || !is_string($rawPayload)) {
            return [];
        }

        try {
            $payload = $this->serializer->unserialize($rawPayload);
        } catch (Throwable $exception) {
            $this->logger->warning(
                'Unable to read DHL tracking history from the shipment payload.',
                $this->getLogContext($shipment, $exception)
            );
            return [];
        }

        if (!is_array($payload)) {
            $this->logger->warning(
                'DHL shipment payload has an invalid structure.',
                $this->getLogContext($shipment)
            );
            return [];
        }

        if (!array_key_exists('trackingEvents', $payload)) {
            return [];
        }

        if (!is_array($payload['trackingEvents'])) {
            $this->logger->warning(
                'DHL tracking history has an invalid payload structure.',
                $this->getLogContext($shipment)
            );
            return [];
        }

        $events = $payload['trackingEvents'];
        $validEvents = array_filter($events, 'is_array');
        if (count($validEvents) !== count($events)) {
            $this->logger->warning(
                'DHL tracking history contains invalid event data.',
                $this->getLogContext($shipment)
            );
        }

        return array_values($validEvents);
    }

    /**
     * Get DHL shipment ID / package number.
     *
     * @return string
     */
    public function getShipmentId(): string
    {
        $shipment = $this->shipmentData->getShipment();

        return (string) ($shipment?->getShipmentId() ?? '');
    }

    /**
     * Build log context without including the serialized shipment payload.
     *
     * @param ShipmentDataInterface $shipment
     * @param Throwable|null $exception
     *
     * @return array
     */
    private function getLogContext(ShipmentDataInterface $shipment, ?Throwable $exception = null): array
    {
        $context = ['entity_id' => $shipment->getEntityId()];
        if ($exception !== null) {
            $context['exception_type'] = $exception::class;
            $context['exception_code'] = $exception->getCode();
        }

        return $context;
    }
}
