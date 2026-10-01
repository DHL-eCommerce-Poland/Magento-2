<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\DhlShipmentData;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ShipmentDateSanitizer;
use Ideo\Dhl\Model\Shipment\ShipmentDataValidator;
use InvalidArgumentException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;

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
     * @param ShipmentDateSanitizer $dateSanitizer
     * @param ShipmentDataValidator $shipmentDataValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentCreationService $shipmentCreationService,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly JsonFactory $jsonFactory,
        private readonly SerializerInterface $serializer,
        private readonly TimezoneInterface $timezone,
        private readonly ShipmentDateSanitizer $dateSanitizer,
        private readonly ShipmentDataValidator $shipmentDataValidator,
        private readonly LoggerInterface $logger
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
        $request = $this->getRequest();
        $isAjax = $request->isXmlHttpRequest();
        $resultJson = $this->jsonFactory->create();

        $postValue = $request->getPostValue();

        if (is_array($postValue) && (isset($postValue['shipmentSettings']) || isset($postValue['sender']))) {
            $payload = $postValue;
        } else {
            $payload = $request->getParam('shipment_data');
            if ($payload === null) {
                $payload = $request->getParam('data')
                    ?? (is_array($postValue) ? ($postValue['data'] ?? null) : null);
            }
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

        // Validate raw payload against commas in numeric fields
        try {
            $this->shipmentDataValidator->validateRawPayload($payload);
        } catch (LocalizedException $e) {
            $errorMsg = $e->getMessage();
            if ($isAjax) {
                return $resultJson->setData(['success' => false, 'message' => $errorMsg]);
            }
            $this->messageManager->addErrorMessage($errorMsg);
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        // Validate and sanitize shipmentDate (must be valid and not in the past)
        if (isset($payload['shipmentSettings']) && is_array($payload['shipmentSettings'])) {
            $today = $this->timezone->scopeDate()->format('Y-m-d');
            $rawDate = trim((string)($payload['shipmentSettings']['shipmentDate'] ?? ''));
            $finalDate = $this->dateSanitizer->sanitize($rawDate, $today);

            if (!empty($rawDate) && $finalDate < $today) {
                $msg = __('Shipment date cannot be in the past.');
                if ($isAjax) {
                    return $resultJson->setData(['success' => false, 'message' => (string) $msg]);
                }
                $this->messageManager->addErrorMessage($msg);
                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }

            $payload['shipmentSettings']['shipmentDate'] = $finalDate;

            if (!empty($payload['shipmentSettings']['pickupHourFrom'])) {
                $hourFrom = (string) $payload['shipmentSettings']['pickupHourFrom'];
                $payload['shipmentSettings']['pickupHourFrom'] = substr($hourFrom, 0, 5);
            }
            if (!empty($payload['shipmentSettings']['pickupHourTo'])) {
                $hourTo = (string) $payload['shipmentSettings']['pickupHourTo'];
                $payload['shipmentSettings']['pickupHourTo'] = substr($hourTo, 0, 5);
            }

            if (isset($payload['sender']) && is_array($payload['sender'])) {
                $payload['sender']['pickupHourFrom'] = $payload['shipmentSettings']['pickupHourFrom'] ?? null;
                $payload['sender']['pickupHourTo'] = $payload['shipmentSettings']['pickupHourTo'] ?? null;
            }
        }

        $paramId = $this->getRequest()->getParam('entity_id')
            ?? $this->getRequest()->getParam('id')
            ?? ($payload['entity_id'] ?? 0);
        $entityId = (int) $paramId;

        try {
            $shipmentData = DhlShipmentData::fromArray($payload);

            // Extract template IDs from request or nested draft payload
            $rawSenderId = $this->getRequest()->getParam('drop_off_point_template_id')
                ?? ($payload['sender']['selectedDropOffPointId'] ?? $payload['drop_off_point_template_id'] ?? null);
            $rawShipmentId = $this->getRequest()->getParam('delivery_service_template_id')
                ?? ($payload['shipmentSettings']['selectedDeliveryServiceId']
                    ?? $payload['delivery_service_template_id'] ?? null);
            $rawCustomsId = $this->getRequest()->getParam('customs_template_id')
                ?? ($payload['customs']['selectedCustomsTemplateId'] ?? $payload['customs_template_id'] ?? null);

            $dropOffPointTemplateId = (!empty($rawSenderId) && trim((string)$rawSenderId) !== '')
                ? (string)$rawSenderId
                : null;
            $deliveryServiceTemplateId = (!empty($rawShipmentId) && trim((string)$rawShipmentId) !== '')
                ? (string)$rawShipmentId
                : null;
            $customsTemplateId = (!empty($rawCustomsId) && trim((string)$rawCustomsId) !== '')
                ? (string)$rawCustomsId
                : null;

            // 1. Resolve environment configuration (sandbox vs production)
            $isSandbox = (bool) $this->scopeConfig->getValue('carriers/dhlpoland/sandbox');
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
                    $dropOffPointTemplateId,
                    $deliveryServiceTemplateId,
                    $customsTemplateId
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
                $redirectPath = $entityId > 0 ? '*/*/edit' : '*/*/index';
                return $this->resultRedirectFactory->create()
                    ->setPath($redirectPath, ['entity_id' => $entityId]);
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
                $redirectPath = $entityId > 0 ? '*/*/edit' : '*/*/index';
                return $this->resultRedirectFactory->create()
                    ->setPath($redirectPath, ['entity_id' => $entityId]);
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
            $this->logger->error(
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
            $redirectPath = $entityId > 0 ? '*/*/edit' : '*/*/index';
            return $this->resultRedirectFactory->create()
                ->setPath($redirectPath, ['entity_id' => $entityId]);
        }
    }
}
