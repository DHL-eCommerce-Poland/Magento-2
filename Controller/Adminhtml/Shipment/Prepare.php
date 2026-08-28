<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Template\TemplateManager;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class Prepare extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param TemplateManager $templateManager
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TemplateManager $templateManager,
        private readonly JsonFactory $jsonFactory,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute prepare shipment modal payload action.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();
        $orderId = (int)$this->getRequest()->getParam('order_id');

        if (!$orderId) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Order ID is required.')->render(),
            ]);
        }

        try {
            $order = $this->orderRepository->get($orderId);
            $presetSenderId = $this->getRequest()->getParam('preset_sender_id')
                ? (int)$this->getRequest()->getParam('preset_sender_id')
                : null;
            $presetShipmentId = $this->getRequest()->getParam('preset_shipment_id')
                ? (int)$this->getRequest()->getParam('preset_shipment_id')
                : null;

            $shipmentData = $this->shipmentDataService->prepareShipmentData(
                $orderId,
                $presetSenderId,
                $presetShipmentId
            );

            $senderTemplates = $this->templateManager->getSenderTemplatesOptions();
            $shipmentTemplates = $this->templateManager->getShipmentTemplatesOptions();
            $defaultDropOffPointId = $this->templateManager->getDefaultDropOffPointId();
            $defaultDeliveryServiceId = $this->templateManager->getMatchingDeliveryServiceIdForOrder($order);

            return $resultJson->setData([
                'success' => true,
                'data' => $shipmentData->toArray(),
                'senderTemplates' => $senderTemplates,
                'senderPresets' => $senderTemplates,
                'shipmentTemplates' => $shipmentTemplates,
                'shipmentPresets' => $shipmentTemplates,
                'defaultDropOffPointId' => $defaultDropOffPointId ? (string)$defaultDropOffPointId : null,
                'defaultDeliveryServiceId' => $defaultDeliveryServiceId ? (string)$defaultDeliveryServiceId : null,
                'countryOptions' => $this->templateManager->getCountryOptions(),
                'serviceCodeOptions' => $this->templateManager->getServiceCodeOptions(),
                'labelTypeOptions' => $this->templateManager->getLabelTypeOptions(),
                'packageTypeOptions' => $this->templateManager->getPackageTypeOptions(),
                'addressTypeOptions' => $this->templateManager->getAddressTypeOptions(),
                'contentSourceOptions' => $this->templateManager->getContentSourceOptions(),
            ]);
        } catch (Exception $e) {
            $this->logger?->error('Error preparing DHL shipment data: ' . $e->getMessage(), ['exception' => $e]);
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
