<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\ReturnShipmentCreationService;
use Ideo\Dhl\Model\Shipment\ReturnShipmentDataBuilder;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
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
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentDataService $shipmentDataService,
        private readonly ReturnShipmentDataBuilder $returnBuilder,
        private readonly ReturnShipmentCreationService $returnCreationService,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute return shipment creation action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int) $this->getRequest()->getParam('entity_id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid shipment entity ID.'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $existingEntity = $this->shipmentDataService->getShipmentEntity($entityId);
            $shipmentData = $this->shipmentDataService->loadShipmentDataFromEntity($existingEntity);

            if ($shipmentData === null) {
                $this->messageManager->addErrorMessage(
                    __('Could not load shipment data for return shipment creation.')
                );
                return $resultRedirect->setPath('*/*/index');
            }

            $returnShipmentData = $this->returnBuilder->buildReturnShipmentData($shipmentData);
            $primaryWaybill = $existingEntity->getShipmentId();

            $isSandbox = (bool) $this->scopeConfig->getValue('carriers/dhlpoland/sandbox');
            $environment = $isSandbox ? 'sandbox' : 'production';

            $result = $this->returnCreationService->createReturnShipment(
                returnShipmentData: $returnShipmentData,
                environment: $environment,
                expirationDays: 14,
                primaryWaybillNumber: $primaryWaybill,
                originalEntityId: $entityId
            );

            if ($result->isSuccess()) {
                $this->messageManager->addSuccessMessage(
                    __('Return shipment created successfully. Return Package Number: %1', $result->getShipmentId())
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Failed to create return shipment: %1', $result->getErrorMessage() ?: __('Unknown error'))
                );
            }
        } catch (Exception $e) {
            $this->logger?->error('Error creating DHL return shipment: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('Failed to create return shipment: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
