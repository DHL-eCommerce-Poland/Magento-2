<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use DhlPoland\Dhl\Model\Shipment\DhlShipmentData;
use DhlPoland\Dhl\Model\Shipment\ShipmentCreationService;
use DhlPoland\Dhl\Model\Shipment\ShipmentDateSanitizer;
use DhlPoland\Dhl\Model\Shipment\ShipmentDataValidator;
use InvalidArgumentException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
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
    public const ADMIN_RESOURCE = 'DhlPoland_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentCreationService $shipmentCreationService
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

        $paramId = $this->getRequest()->getParam('entity_id')
            ?? $this->getRequest()->getParam('id')
            ?? (is_array($payload) ? ($payload['entity_id'] ?? 0) : 0);
        $entityId = (int) $paramId;

        if (empty($payload) || !is_array($payload)) {
            $msg = __('Invalid shipment data provided.')->render();
            if ($isAjax) {
                return $resultJson->setData(['success' => false, 'message' => $msg]);
            }
            $this->messageManager->addErrorMessage($msg);
            $redirectPath = $entityId > 0 ? '*/*/edit' : '*/*/index';
            return $this->resultRedirectFactory->create()
                ->setPath($redirectPath, ['entity_id' => $entityId]);
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
            $redirectPath = $entityId > 0 ? '*/*/edit' : '*/*/index';
            return $this->resultRedirectFactory->create()
                ->setPath($redirectPath, ['entity_id' => $entityId]);
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
                $redirectPath = $entityId > 0 ? '*/*/edit' : '*/*/index';
                return $this->resultRedirectFactory->create()
                    ->setPath($redirectPath, ['entity_id' => $entityId]);
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

        // Sanitize customs fields: strip individual clearance fields when not individual ('I')
        if (isset($payload['customs']) && is_array($payload['customs'])) {
            $customsType = (string)($payload['customs']['customsType'] ?? 'U');
            if ($customsType === 'U' || $customsType === 'S') {
                $payload['customs']['nipNr'] = null;
                $payload['customs']['eoriNr'] = null;
                $payload['customs']['eoriNrReceiver'] = null;
                $payload['customs']['shipperNip'] = null;
                $payload['customs']['shipperEori'] = null;
                $payload['customs']['receiverEori'] = null;
                $payload['customs']['invoiceNr'] = null;
                $payload['customs']['invoiceDate'] = null;
                $payload['customs']['invoiceFile'] = null;
                $payload['customs']['invoiceContent'] = false;
            } elseif (!empty($payload['customs']['invoiceDate'])) {
                $rawInvoiceDate = trim((string)$payload['customs']['invoiceDate']);
                $payload['customs']['invoiceDate'] = $this->dateSanitizer->sanitize(
                    $rawInvoiceDate,
                    $rawInvoiceDate,
                    true
                );
            }
        }

        try {
            $shipmentData = DhlShipmentData::fromArray($payload);

            if ($entityId > 0) {
                $dispatchResult = $this->shipmentCreationService->recreateShipment(
                    $entityId,
                    $shipmentData
                );
            } else {
                $dispatchResult = $this->shipmentCreationService->createShipment(
                    $shipmentData
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
            $createdEntityId = $dispatchResult->getEntityId() ?? ($entityId > 0 ? $entityId : null);
            $successMsg = __('DHL shipment %1 has been created successfully.', $createdShipmentId)->render();

            $this->messageManager->addSuccessMessage($successMsg);

            $redirectUrl = $createdEntityId
                ? $this->getUrl('*/*/edit', ['entity_id' => $createdEntityId])
                : null;

            if ($isAjax) {
                return $resultJson->setData([
                    'success' => true,
                    'shipmentId' => $createdShipmentId,
                    'entityId' => $createdEntityId,
                    'redirectUrl' => $redirectUrl,
                    'status' => $dispatchResult->getOrderStatus() ?: 'created',
                    'message' => $successMsg,
                ]);
            }

            $redirectPath = $createdEntityId ? '*/*/edit' : '*/*/index';
            return $this->resultRedirectFactory->create()
                ->setPath($redirectPath, $createdEntityId ? ['entity_id' => $createdEntityId] : []);
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
