<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Api\DeliveryServiceRepositoryInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Model\Shipment\OrderToShipmentDataMapper;
use Ideo\Dhl\Model\Template\TemplateManager;
use Ideo\Dhl\Model\Template\TemplateVariableResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
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
                        'presetSenderId' => (string)$point->getId(),
                        'sapNumber' => $point->getSapNumber() ?? '',
                        'name' => $point->getSenderName() ?? '',
                        'firstName' => $point->getFirstName() ?? '',
                        'lastName' => $point->getLastName() ?? '',
                        'street' => $point->getStreet() ?? '',
                        'houseNumber' => $point->getHouseNumber() ?? '',
                        'flatNumber' => '',
                        'postalCode' => $point->getPostalCode() ?? '',
                        'city' => $point->getCity() ?? '',
                        'country' => $point->getCountryId() ?? 'PL',
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

                $resolvedContent = (string)($serviceData['content'] ?? 'ORDER_NUMBER');
                $resolvedComment = (string)($serviceData['comment'] ?? '');
                $resolvedReference = (string)($serviceData['reference'] ?? '');
                $resolvedMpk = (string)($serviceData['mpk'] ?? '');

                if ($orderId) {
                    try {
                        $order = $this->orderRepository->get($orderId);
                        $resolvedContent = $this->mapper->resolveContentValue($resolvedContent, $order);
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
                        'serviceType' => $serviceData['service_code'] ?? 'AH',
                        'packageType' => $serviceData['package_type'] ?? 'PACKAGE',
                        'weight' => (float)($serviceData['weight'] ?? 1.0),
                        'width' => isset($serviceData['width']) ? (float)$serviceData['width'] : null,
                        'height' => isset($serviceData['height']) ? (float)$serviceData['height'] : null,
                        'length' => isset($serviceData['length']) ? (float)$serviceData['length'] : null,
                        'isNonStandard' => !empty($serviceData['is_non_standard']),
                        'hasEuroReturn' => !empty($serviceData['has_euro_return']),
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
                        'dropOffPointId' => isset($serviceData['drop_off_point_id'])
                            ? (int)$serviceData['drop_off_point_id']
                            : null,
                        'addressType' => $serviceData['address_type'] ?? 'N',
                        'specialServices' => $additionalServices,
                    ]
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
