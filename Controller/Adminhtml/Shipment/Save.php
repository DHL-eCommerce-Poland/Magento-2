<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Ideo\Dhl\Model\Shipment\ShipmentStatus;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentDataService $shipmentDataService
     * @param ShipmentCreationService $shipmentCreationService
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonFactory $jsonFactory
     * @param SerializerInterface $serializer
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDataService $shipmentDataService,
        private readonly ShipmentCreationService $shipmentCreationService,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly JsonFactory $jsonFactory,
        private readonly SerializerInterface $serializer,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute save shipment action and dispatch directly to DHL API.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();
        $payload = $this->getRequest()->getParam('draft') ?? $this->getRequest()->getParam('shipment_data');

        if (is_string($payload)) {
            try {
                $payload = $this->serializer->unserialize($payload);
            } catch (\InvalidArgumentException $e) {
                $payload = null;
            }
        }

        if (empty($payload) || !is_array($payload)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid shipment data provided.')->render(),
            ]);
        }

        try {
            $shipmentData = DhlShipmentData::fromArray($payload);

            // Extract preset/template IDs from request or nested draft payload
            $rawSenderId = $this->getRequest()->getParam('preset_sender_id')
                ?? ($payload['sender']['selectedDropOffPointId'] ?? $payload['preset_sender_id'] ?? null);
            $rawShipmentId = $this->getRequest()->getParam('preset_shipment_id')
                ?? ($payload['shipmentSettings']['selectedDeliveryServiceId']
                    ?? $payload['preset_shipment_id'] ?? null);

            $presetSenderId = (!empty($rawSenderId) && trim((string)$rawSenderId) !== '')
                ? (string)$rawSenderId
                : null;
            $presetShipmentId = (!empty($rawShipmentId) && trim((string)$rawShipmentId) !== '')
                ? (string)$rawShipmentId
                : null;

            // 1. Resolve environment configuration (sandbox vs production)
            $isSandbox = (bool) $this->scopeConfig->getValue('carriers/dhlparcel/sandbox');
            $environment = $isSandbox ? 'sandbox' : 'production';

            // 2. Dispatch directly to DHL WebAPI2 (createShipments)
            $dispatchResult = $this->shipmentCreationService->createAndDispatchShipment(
                $shipmentData,
                $environment,
                $presetSenderId,
                $presetShipmentId
            );

            if ($dispatchResult->isTimeout()) {
                return $resultJson->setData([
                    'success' => false,
                    'isTimeout' => true,
                    'message' => __(
                        'Connection to DHL timed out. Status set to PENDING_VERIFICATION. '
                        . 'Please verify parcel status in DHL24 portal before re-trying.'
                    )->render(),
                ]);
            }

            if (!$dispatchResult->isSuccess()) {
                $errorMsg = $dispatchResult->getErrorMessage() ?? __('Failed to create shipment in DHL.')->render();
                return $resultJson->setData([
                    'success' => false,
                    'message' => $errorMsg,
                    'errorCode' => $dispatchResult->getErrorCode(),
                ]);
            }

            $waybillNumber = (string) $dispatchResult->getShipmentId();
            $successMsg = __('Przesyłka DHL o numerze %1 została pomyślnie utworzona.', $waybillNumber)->render();

            $this->messageManager->addSuccessMessage($successMsg);

            return $resultJson->setData([
                'success' => true,
                'shipmentId' => $waybillNumber,
                'status' => ShipmentStatus::DISPATCHED,
                'message' => $successMsg,
            ]);
        } catch (Exception $e) {
            $this->logger?->error(
                'Error saving and dispatching DHL shipment: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
