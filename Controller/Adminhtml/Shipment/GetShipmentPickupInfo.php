<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class GetShipmentPickupInfo extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Get saved pickup hours and date for given shipment entity ID from its payload.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();

        try {
            $shipmentId = (int) $this->getRequest()->getParam('shipment_id');
            $shipmentIds = (array) $this->getRequest()->getParam('shipment_ids', []);
            if ($shipmentId <= 0 && !empty($shipmentIds)) {
                $shipmentId = (int) reset($shipmentIds);
            }

            if ($shipmentId <= 0) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Invalid shipment ID.'),
                ]);
            }

            $entity = $this->shipmentDataRepository->getById($shipmentId);
            $rawPayload = $entity->getShipmentData();
            $dataArr = [];

            if (!empty($rawPayload)) {
                if (is_string($rawPayload)) {
                    try {
                        $unserialized = $this->serializer->unserialize($rawPayload);
                        if (is_array($unserialized)) {
                            $dataArr = $unserialized;
                        }
                    } catch (Exception) {
                        $dataArr = [];
                    }
                } elseif (is_array($rawPayload)) {
                    $dataArr = $rawPayload;
                }
            }

            $pickupFrom = (string) (
                $dataArr['shipmentSettings']['pickupHourFrom']
                ?? $dataArr['shipmentSettings']['pickup_time_from']
                ?? $dataArr['sender']['pickupHourFrom']
                ?? $dataArr['sender']['pickup_time_from']
                ?? ''
            );

            $pickupTo = (string) (
                $dataArr['shipmentSettings']['pickupHourTo']
                ?? $dataArr['shipmentSettings']['pickup_time_to']
                ?? $dataArr['sender']['pickupHourTo']
                ?? $dataArr['sender']['pickup_time_to']
                ?? ''
            );

            return $resultJson->setData([
                'success' => true,
                'data' => [
                    'pickup_time_from' => $pickupFrom !== '' ? substr($pickupFrom, 0, 5) : null,
                    'pickup_time_to' => $pickupTo !== '' ? substr($pickupTo, 0, 5) : null,
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('[GetShipmentPickupInfo Controller] Error: ' . $e->getMessage());

            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
