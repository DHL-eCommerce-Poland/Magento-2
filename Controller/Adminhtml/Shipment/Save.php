<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentDataService $shipmentDataService
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDataService $shipmentDataService,
        private readonly JsonFactory $jsonFactory,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute save shipment action.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();
        $payload = $this->getRequest()->getParam('draft') ?? $this->getRequest()->getParam('shipment_data');

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (empty($payload) || !is_array($payload)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid shipment data provided.')->render(),
            ]);
        }

        try {
            $shipmentData = DhlShipmentData::fromArray($payload);
            $presetSenderId = $this->getRequest()->getParam('preset_sender_id');
            $presetShipmentId = $this->getRequest()->getParam('preset_shipment_id');

            $savedEntity = $this->shipmentDataService->saveShipmentData(
                $shipmentData,
                $presetSenderId,
                $presetShipmentId
            );

            return $resultJson->setData([
                'success' => true,
                'shipmentId' => $savedEntity->getEntityId(),
                'status' => $savedEntity->getStatus(),
                'message' => __('DHL shipment data saved successfully. Ready for API dispatch.')->render(),
            ]);
        } catch (Exception $e) {
            $this->logger?->error('Error saving DHL shipment data: ' . $e->getMessage(), ['exception' => $e]);
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
