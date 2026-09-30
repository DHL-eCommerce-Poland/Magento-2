<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
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
     * @param ShipmentDataService $shipmentDataService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TemplateManager $templateManager,
        private readonly JsonFactory $jsonFactory,
        private readonly ShipmentDataService $shipmentDataService,
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

        if ($this->shipmentDataService->hasShipmentForOrder($orderId)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('A DHL shipment has already been created for order #%1.', $orderId)->render(),
            ]);
        }

        try {
            $order = $this->orderRepository->get($orderId);
            $dropOffPointTemplateId = $this->getRequest()->getParam('drop_off_point_template_id')
                ? (int)$this->getRequest()->getParam('drop_off_point_template_id')
                : null;
            $deliveryServiceTemplateId = $this->getRequest()->getParam('delivery_service_template_id')
                ? (int)$this->getRequest()->getParam('delivery_service_template_id')
                : null;

            $shipmentData = $this->shipmentDataService->prepareShipmentData(
                $orderId,
                $dropOffPointTemplateId,
                $deliveryServiceTemplateId
            );

            $shippingMethod = (string)($order->getShippingMethod() ?? '');
            $senderTemplates = $this->templateManager->getSenderTemplatesOptions();
            $shipmentTemplates = $this->templateManager->getShipmentTemplatesOptions($shippingMethod);
            $defaultDropOffPointId = $this->templateManager->getDefaultDropOffPointId();
            $defaultDeliveryServiceId = $this->templateManager->getMatchingDeliveryServiceIdForOrder($order);
            $defaultCustomsTemplateId = $this->templateManager->getMatchingCustomsTemplateIdForOrder($order);

            return $resultJson->setData([
                'success' => true,
                'data' => $shipmentData->toArray(),
                'senderTemplates' => $senderTemplates,
                'senderPresets' => $senderTemplates,
                'shipmentTemplates' => $shipmentTemplates,
                'shipmentPresets' => $shipmentTemplates,
                'defaultDropOffPointId' => $defaultDropOffPointId ? (string)$defaultDropOffPointId : null,
                'defaultDeliveryServiceId' => $defaultDeliveryServiceId ? (string)$defaultDeliveryServiceId : null,
                'defaultCustomsTemplateId' => $defaultCustomsTemplateId ? (string)$defaultCustomsTemplateId : null,
                'countryOptions' => $this->templateManager->getCountryOptions(),
                'serviceCodeOptions' => $this->templateManager->getServiceCodeOptions(),
                'labelTypeOptions' => $this->templateManager->getLabelTypeOptions(),
                'packageTypeOptions' => $this->templateManager->getPackageTypeOptions(),
                'addressTypeOptions' => $this->templateManager->getAddressTypeOptions(),
                'shipmentAddressTypeOptions' => $this->templateManager->getShipmentAddressTypeOptions(),
                'returnQrCodeTypeOptions' => $this->templateManager->getReturnQrCodeTypeOptions(),
                'returnBookCourierOptions' => $this->templateManager->getReturnBookCourierOptions(),
                'customsTemplates' => $this->templateManager->getCustomsTemplatesOptions(),
                'customsTypeOptions' => $this->templateManager->getCustomsTypeOptions(),
                'categoryOfItemOptions' => $this->templateManager->getCategoryOfItemOptions(),
                'customsCurrencyOptions' => $this->templateManager->getCustomsCurrencyOptions(),
                'internationalMapPointTypeOptions' => $this->templateManager->getInternationalMapPointTypeOptions(),
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
