<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Model\CustomsTemplateRepository;
use Ideo\Dhl\Model\MethodCatalog;
use Ideo\Dhl\Model\Shipment\OrderToShipmentDataMapper;
use Ideo\Dhl\Model\Template\TemplateManager;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class LoadTemplate extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     * @param DeliveryServiceRepositoryInterface $deliveryServiceRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderToShipmentDataMapper $mapper
     * @param TemplateManager $templateManager
     * @param JsonFactory $jsonFactory
     * @param TemplateVariableResolver $variableResolver
     * @param MethodCatalog $methodCatalog
     * @param CustomsTemplateRepository $customsTemplateRepository
     * @param SerializerInterface $serializer
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository,
        private readonly DeliveryServiceRepositoryInterface $deliveryServiceRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderToShipmentDataMapper $mapper,
        private readonly TemplateManager $templateManager,
        private readonly JsonFactory $jsonFactory,
        private readonly TemplateVariableResolver $variableResolver,
        private readonly MethodCatalog $methodCatalog,
        private readonly CustomsTemplateRepository $customsTemplateRepository,
        private readonly SerializerInterface $serializer,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute load template action.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();
        $templateId = (int)($this->getRequest()->getParam('template_id')
            ?? $this->getRequest()->getParam('preset_id'));
        $templateType = (string)($this->getRequest()->getParam('template_type')
            ?? $this->getRequest()->getParam('preset_type'));
        $orderId = (int)$this->getRequest()->getParam('order_id');

        if (!$templateId || empty($templateType)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Missing template_id or template_type parameter.'),
            ]);
        }

        try {
            if ($templateType === 'sender') {
                $point = $this->dropOffPointRepository->getById($templateId);

                return $resultJson->setData([
                    'success' => true,
                    'data' => [
                        'presetSenderId' => (string)($point->getEntityId() ?? $point->getId()),
                        'sapNumber' => $point->getSapNumber() ?? '',
                        'name' => $point->getSenderName() ?? '',
                        'firstName' => $point->getFirstName() ?? '',
                        'lastName' => $point->getLastName() ?? '',
                        'street' => $point->getStreet() ?? '',
                        'houseNumber' => $point->getHouseNumber() ?? '',
                        'flatNumber' => '',
                        'postalCode' => $point->getPostalCode() ?? '',
                        'city' => $point->getCity() ?? '',
                        'country' => 'PL',
                        'phone' => $point->getPhone() ?? '',
                        'email' => $point->getEmail() ?? '',
                        'pickupHourFrom' => $point->getPickupTimeFrom() ?: null,
                        'pickupHourTo' => $point->getPickupTimeTo() ?: null,
                    ],
                ]);
            }

            if ($templateType === 'shipment') {
                $service = $this->deliveryServiceRepository->getById($templateId);
                $serviceData = $service->getData();
                $additionalServices = $this->templateManager->getSpecialServicesTemplate($templateId) ?: [];

                $resolvedContent = (string)($serviceData['content'] ?? '');
                $resolvedComment = (string)($serviceData['comment'] ?? '');
                $resolvedReference = (string)($serviceData['reference'] ?? '');
                $resolvedMpk = (string)($serviceData['mpk'] ?? '');

                $shippingMethod = null;
                if ($orderId) {
                    try {
                        $order = $this->orderRepository->get($orderId);
                        $shippingMethod = (string)($order->getShippingMethod() ?? '');
                        $resolvedContent = $resolvedContent !== ''
                            ? $this->variableResolver->resolve($resolvedContent, $order)
                            : '';
                        $resolvedComment = $resolvedComment !== ''
                            ? $this->variableResolver->resolve($resolvedComment, $order)
                            : '';
                        $resolvedReference = $resolvedReference !== ''
                            ? $this->variableResolver->resolve($resolvedReference, $order)
                            : '';
                        $resolvedMpk = $resolvedMpk !== ''
                            ? $this->variableResolver->resolve($resolvedMpk, $order)
                            : '';
                    } catch (Exception $e) {
                        $this->logger?->warning(
                            'Could not resolve order content for template: ' . $e->getMessage(),
                            ['exception' => $e]
                        );
                    }
                }

                return $resultJson->setData([
                    'success' => true,
                    'data' => [
                        'serviceType' => $this->methodCatalog->getServiceCodeForMethod($shippingMethod),
                        'content' => $resolvedContent,
                        'comment' => $resolvedComment,
                        'reference' => $resolvedReference,
                        'mpk' => $resolvedMpk,
                        'labelType' => $serviceData['label_type'] ?? 'BLP',
                        'autoCreateReturnShipment' => !empty($serviceData['auto_create_return_shipment']),
                        'returnLabelType' => $service->getReturnLabelType() ?: 'BLP',
                        'returnQrCodeType' => $service->getReturnQrCodeType() ?: 'QR_PDF',
                        'returnLabelExpDays' => $service->getReturnLabelExpDays() ?? 14,
                        'returnBookCourier' => $service->getReturnBookCourier() ?? 0,
                        'packages' => $service->getPackages() ?: [],
                        'addressType' => $serviceData['address_type'] ?? 'N',
                        'specialServices' => $additionalServices,
                    ]
                ]);
            }

            if ($templateType === 'customs') {
                $template = $this->customsTemplateRepository->getById($templateId);
                $customsItems = [];
                $rawItems = $template->getCustomsItems();
                if (is_string($rawItems) && !empty($rawItems)) {
                    try {
                        $decoded = $this->serializer->unserialize($rawItems);
                        if (is_array($decoded)) {
                            if (isset($decoded['customs_items']) && is_array($decoded['customs_items'])) {
                                $decoded = $decoded['customs_items'];
                            } elseif (count($decoded) === 1
                                && isset($decoded[0])
                                && is_array($decoded[0])
                                && !isset($decoded[0]['nameEn'])
                                && !isset($decoded[0]['namePl'])
                            ) {
                                $decoded = $decoded[0];
                            }
                            $customsItems = array_values($decoded);
                        }
                    } catch (Exception $e) {
                        $customsItems = [];
                    }
                } elseif (is_array($rawItems)) {
                    $customsItems = $rawItems;
                }

                $defaultTariff = $template->getDefaultTariffCode() ?? '';
                $countryOrigin = $template->getCountryOfOrigin() ?: 'PL';

                // Ensure item defaults if missing in individual row
                $formattedItems = array_map(function ($item) use ($defaultTariff, $countryOrigin) {
                    return [
                        'namePl' => (string)($item['namePl'] ?? ''),
                        'nameEn' => (string)($item['nameEn'] ?? ''),
                        'quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 1,
                        'weight' => isset($item['weight']) ? (float)$item['weight'] : 0.0,
                        'value' => isset($item['value']) ? (float)$item['value'] : 0.0,
                        'tariffCode' => (string)(!empty($item['tariffCode']) ? $item['tariffCode'] : $defaultTariff),
                        'countryOfOrigin' => (string)(!empty($item['countryOfOrigin'])
                            ? $item['countryOfOrigin']
                            : $countryOrigin),
                    ];
                }, $customsItems);

                $costs = $template->getCostsOfShipment() !== null
                    ? (float)$template->getCostsOfShipment()
                    : null;
                $grossWeight = $template->getGrossWeight() !== null
                    ? (float)$template->getGrossWeight()
                    : null;
                $isIndividual = ($template->getCustomsType() ===
                    \Ideo\Dhl\Model\Source\CustomsType::TYPE_INDIVIDUAL);

                return $resultJson->setData([
                    'success' => true,
                    'data' => [
                        'selectedCustomsTemplateId' => (int)$template->getId(),
                        'customsType' => $template->getCustomsType() ?: 'U',
                        'categoryOfItem' => $template->getCategoryOfItem() ?: '11',
                        'currency' => $template->getCurrency() ?: 'EUR',
                        'countryOfOrigin' => $countryOrigin,
                        'defaultTariffCode' => $defaultTariff,
                        'costsOfShipment' => $costs,
                        'grossWeight' => $grossWeight,
                        'firstName' => $template->getContactFirstName() ?? '',
                        'secondaryName' => $template->getContactLastName() ?? '',
                        'nipNr' => $template->getShipperNip() ?? '',
                        'eoriNr' => $template->getShipperEori() ?? '',
                        'vatRegistrationNumber' => $template->getShipperVatNo() ?? '',
                        'eoriNrReceiver' => $template->getReceiverEori() ?? '',
                        'vatRegistrationNumberReceiver' => $template->getReceiverVatNo() ?? '',
                        'invoiceNr' => '',
                        'invoiceDate' => date('Y-m-d'),
                        'invoiceFile' => '',
                        'additionalInfo' => $template->getAdditionalInfo() ?? '',
                        'notExceedValue' => $template->getNotExceedValue() !== null
                            ? (bool)$template->getNotExceedValue() : true,
                        'notProhibitedGoods' => $template->getNotProhibitedGoods() !== null
                            ? (bool)$template->getNotProhibitedGoods() : true,
                        'notRestrictedGoods' => $template->getNotRestrictedGoods() !== null
                            ? (bool)$template->getNotRestrictedGoods() : true,
                        'invoiceContent' => $isIndividual,
                        'customsItems' => $formattedItems,
                    ],
                ]);
            }

            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid template type specified.')->render(),
            ]);
        } catch (Exception $e) {
            $this->logger?->error('Error loading DHL template: ' . $e->getMessage(), ['exception' => $e]);
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
