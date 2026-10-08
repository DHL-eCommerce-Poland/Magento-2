<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\Data\ShipmentSettingsData;
use Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ReturnShipmentDataBuilder;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class CreateReturnShipment extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentDataService $shipmentDataService
     * @param ReturnShipmentDataBuilder $returnBuilder
     * @param ReturnShipmentCreationService $returnCreationService
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDataService $shipmentDataService,
        private readonly ReturnShipmentDataBuilder $returnBuilder,
        private readonly ReturnShipmentCreationService $returnCreationService,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute return shipment creation action.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $request = $this->getRequest();
        $isAjax = $request->isXmlHttpRequest() || (bool) $request->getParam('isAjax');
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int) $request->getParam('entity_id');

        if ($entityId <= 0) {
            $msg = __('Invalid shipment entity ID.');
            if ($isAjax) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) $msg,
                ]);
            }
            $this->messageManager->addErrorMessage($msg);
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $existingEntity = $this->shipmentDataService->getShipmentEntity($entityId);
            $shipmentData = $this->shipmentDataService->loadShipmentDataFromEntity($existingEntity);

            if ($shipmentData === null) {
                $msg = __('Could not load shipment data for return shipment creation.');
                if ($isAjax) {
                    return $this->resultJsonFactory->create()->setData([
                        'success' => false,
                        'message' => (string) $msg,
                    ]);
                }
                $this->messageManager->addErrorMessage($msg);
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $entityId]);
            }

            $returnShipmentData = $this->returnBuilder->buildReturnShipmentData($shipmentData);
            $primaryWaybill = $existingEntity->getShipmentId();

            $isSandbox = (bool) $this->scopeConfig->getValue('carriers/dhlpoland/sandbox');
            $environment = $isSandbox ? 'sandbox' : 'production';

            $result = $this->returnCreationService->createReturnShipment(
                returnShipmentData: $returnShipmentData,
                environment: $environment,
                expirationDays: ShipmentSettingsData::DEFAULT_RETURN_LABEL_EXP_DAYS,
                primaryWaybillNumber: $primaryWaybill,
                originalEntityId: $entityId
            );

            if ($result->isSuccess()) {
                $successMsg = __(
                    'Return shipment created successfully. Return Package Number: %1',
                    $result->getShipmentId()
                );
                $this->messageManager->addSuccessMessage($successMsg);
                if ($isAjax) {
                    return $this->resultJsonFactory->create()->setData([
                        'success' => true,
                        'message' => (string) $successMsg,
                        'shipmentId' => $result->getShipmentId(),
                    ]);
                }
            } else {
                $errorMsg = __(
                    'Failed to create return shipment: %1',
                    $result->getErrorMessage() ?: __('Unknown error')
                );
                if ($isAjax) {
                    return $this->resultJsonFactory->create()->setData([
                        'success' => false,
                        'message' => (string) $errorMsg,
                    ]);
                }
                $this->messageManager->addErrorMessage($errorMsg);
            }
        } catch (Exception $e) {
            $this->logger->error('Error creating DHL return shipment: ' . $e->getMessage(), ['exception' => $e]);
            $errorMsg = __('Failed to create return shipment: %1', $e->getMessage());
            if ($isAjax) {
                return $this->resultJsonFactory->create()->setData([
                    'success' => false,
                    'message' => (string) $errorMsg,
                ]);
            }
            $this->messageManager->addErrorMessage($errorMsg);
        }

        return $resultRedirect->setPath('*/*/edit', ['entity_id' => $entityId]);
    }
}
