<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use InvalidArgumentException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Save extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentCreationService $shipmentCreationService
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonFactory $jsonFactory
     * @param SerializerInterface $serializer
     * @param TimezoneInterface $timezone
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentCreationService $shipmentCreationService,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly JsonFactory $jsonFactory,
        private readonly SerializerInterface $serializer,
        private readonly TimezoneInterface $timezone,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute save shipment action and dispatch directly to DHL API.
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $isAjax = (method_exists($this->getRequest(), 'isXmlHttpRequest') && $this->getRequest()->isXmlHttpRequest())
            || (bool)$this->getRequest()->getParam('isAjax')
            || $this->getRequest()->getParam('draft') !== null;
        $resultJson = $this->jsonFactory->create();

        $postValue = method_exists($this->getRequest(), 'getPostValue') ? $this->getRequest()->getPostValue() : null;

        if (is_array($postValue) && (isset($postValue['shipmentSettings']) || isset($postValue['sender']))) {
            $payload = $postValue;
        } else {
            $payload = $this->getRequest()->getParam('draft')
                ?? $this->getRequest()->getParam('shipment_data')
                ?? $this->getRequest()->getParam('data')
                ?? (is_array($postValue) ? ($postValue['data'] ?? $postValue) : null);
        }

        if (is_string($payload)) {
            try {
                $payload = $this->serializer->unserialize($payload);
            } catch (InvalidArgumentException $e) {
                $payload = null;
            }
        }

        if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        if (empty($payload) || !is_array($payload)) {
            $msg = __('Invalid shipment data provided.')->render();
            if ($isAjax) {
                return $resultJson->setData(['success' => false, 'message' => $msg]);
            }
            $this->messageManager->addErrorMessage($msg);
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        // Validate and sanitize shipmentDate (must be valid and not in the past)
        if (isset($payload['shipmentSettings']) && is_array($payload['shipmentSettings'])) {
            $today = $this->timezone->scopeDate()->format('Y-m-d');
            $rawDate = trim((string)($payload['shipmentSettings']['shipmentDate'] ?? ''));

            if (!empty($rawDate)) {
                try {
                    $parsedDate = $this->timezone->date($rawDate)->format('Y-m-d');
                } catch (Throwable) {
                    $msg = __('Invalid shipment date format provided.');
                    if ($isAjax) {
                        return $resultJson->setData(['success' => false, 'message' => (string) $msg]);
                    }
                    $this->messageManager->addErrorMessage($msg);
                    return $this->resultRedirectFactory->create()->setPath('*/*/index');
                }

                if ($parsedDate < $today) {
                    $msg = __('Shipment date cannot be in the past.');
                    if ($isAjax) {
                        return $resultJson->setData(['success' => false, 'message' => (string) $msg]);
                    }
                    $this->messageManager->addErrorMessage($msg);
                    return $this->resultRedirectFactory->create()->setPath('*/*/index');
                }
                $finalDate = $parsedDate;
            } else {
                $finalDate = $today;
            }
            $payload['shipmentSettings']['shipmentDate'] = $finalDate;

            if (!empty($payload['shipmentSettings']['pickupHourFrom'])) {
                $payload['shipmentSettings']['pickupHourFrom'] = substr((string)$payload['shipmentSettings']['pickupHourFrom'], 0, 5);
            }
            if (!empty($payload['shipmentSettings']['pickupHourTo'])) {
                $payload['shipmentSettings']['pickupHourTo'] = substr((string)$payload['shipmentSettings']['pickupHourTo'], 0, 5);
            }

            if (isset($payload['sender']) && is_array($payload['sender'])) {
                $payload['sender']['pickupHourFrom'] = $payload['shipmentSettings']['pickupHourFrom'] ?? null;
                $payload['sender']['pickupHourTo'] = $payload['shipmentSettings']['pickupHourTo'] ?? null;
            }
        }

        $entityId = (int) ($this->getRequest()->getParam('entity_id') ?? $this->getRequest()->getParam('id') ?? ($payload['entity_id'] ?? 0));

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

            if ($entityId > 0) {
                $dispatchResult = $this->shipmentCreationService->recreateShipment(
                    $entityId,
                    $shipmentData,
                    $environment
                );
            } else {
                $dispatchResult = $this->shipmentCreationService->createShipment(
                    $shipmentData,
                    $environment,
                    $presetSenderId,
                    $presetShipmentId
                );
            }

            if ($dispatchResult->isTimeout()) {
                $msg = __(
                    'Connection to DHL timed out. Please verify parcel status in DHL24 portal before re-trying.'
                )->render();

                if ($isAjax) {
                    return $resultJson->setData([
                        'success' => false,
                        'isTimeout' => true,
                        'message' => $msg,
                    ]);
                }

                $this->messageManager->addErrorMessage($msg);
                return $this->resultRedirectFactory->create()->setPath($entityId > 0 ? '*/*/edit' : '*/*/index', ['entity_id' => $entityId]);
            }

            if (!$dispatchResult->isSuccess()) {
                $errorMsg = $dispatchResult->getErrorMessage() ?? __('Failed to create shipment in DHL.')->render();

                if ($isAjax) {
                    return $resultJson->setData([
                        'success' => false,
                        'message' => $errorMsg,
                        'errorCode' => $dispatchResult->getErrorCode(),
                    ]);
                }

                $this->messageManager->addErrorMessage($errorMsg);
                return $this->resultRedirectFactory->create()->setPath($entityId > 0 ? '*/*/edit' : '*/*/index', ['entity_id' => $entityId]);
            }

            $createdShipmentId = (string) $dispatchResult->getShipmentId();
            $successMsg = __('DHL shipment %1 has been created successfully.', $createdShipmentId)->render();

            $this->messageManager->addSuccessMessage($successMsg);

            if ($isAjax) {
                return $resultJson->setData([
                    'success' => true,
                    'shipmentId' => $createdShipmentId,
                    'status' => $dispatchResult->getOrderStatus() ?: 'created',
                    'message' => $successMsg,
                ]);
            }

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        } catch (Exception $e) {
            $this->logger?->error(
                'Error saving and dispatching DHL shipment: ' . $e->getMessage(),
                ['exception' => $e]
            );

            if ($isAjax) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->messageManager->addErrorMessage(__('Error saving DHL shipment: %1', $e->getMessage()));
            return $this->resultRedirectFactory->create()->setPath($entityId > 0 ? '*/*/edit' : '*/*/index', ['entity_id' => $entityId]);
        }
    }
}
