<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Shipment\CourierPickupTimeResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class GetPickupHours extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param CourierPickupTimeResolver $pickupTimeResolver
     * @param DropOffPointRepositoryInterface $dropOffPointRepository
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CourierPickupTimeResolver $pickupTimeResolver,
        private readonly DropOffPointRepositoryInterface $dropOffPointRepository,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Get available courier pickup hours via AJAX.
     *
     * @return Json
     */
    // phpcs:ignore Generic.Metrics.NestingLevel.TooHigh
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();

        try {
            $postCode = (string) $this->getRequest()->getParam('post_code');
            $senderId = (int) $this->getRequest()->getParam('sender_id');
            $shipmentId = (int) $this->getRequest()->getParam('shipment_id');
            $shipmentIds = (array) $this->getRequest()->getParam('shipment_ids', []);
            if ($shipmentId > 0 && !in_array($shipmentId, $shipmentIds, true)) {
                $shipmentIds[] = $shipmentId;
            }
            if ($shipmentId <= 0 && !empty($shipmentIds)) {
                $shipmentId = (int) reset($shipmentIds);
            }
            $requestedDate = (string) $this->getRequest()->getParam('date');

            $postalCodes = [];

            if (!empty($postCode)) {
                $postalCodes[] = $postCode;
            } elseif ($senderId > 0) {
                try {
                    $sender = $this->dropOffPointRepository->getById($senderId);
                    if (!empty($sender->getPostalCode())) {
                        $postalCodes[] = (string) $sender->getPostalCode();
                    }
                } catch (Exception $e) {
                    $this->logger->debug('[GetPickupHours] DropOffPoint fallback failed: ' . $e->getMessage());
                }
            }

            if (empty($postalCodes) && !empty($shipmentIds)) {
                foreach ($shipmentIds as $sId) {
                    try {
                        $shipment = $this->shipmentDataRepository->getById((int)$sId);
                        $payload = $shipment->getShipmentData();
                        if (!empty($payload)) {
                            $unserialized = is_array($payload) ? $payload : json_decode((string)$payload, true);
                            if (is_array($unserialized) && !empty($unserialized['sender']['postalCode'])) {
                                $postalCodes[] = (string) $unserialized['sender']['postalCode'];
                            }
                        }
                    } catch (Exception $e) {
                        $this->logger->debug(
                            sprintf('[GetPickupHours] Shipment #%s fallback failed: %s', $sId, $e->getMessage())
                        );
                    }
                }
            }

            if (empty($postalCodes)) {
                $defaultSender = $this->dropOffPointRepository->getDefault();
                if ($defaultSender && !empty($defaultSender->getPostalCode())) {
                    $postalCodes[] = (string) $defaultSender->getPostalCode();
                }
            }

            if (empty($postalCodes)) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('Sender postal code is required to retrieve courier pickup hours.'),
                ]);
            }

            $environment = (string) $this->scopeConfig->getValue('carriers/dhlpoland/environment') ?: 'sandbox';

            $resultData = $this->pickupTimeResolver->resolveAvailableHoursForPostalCodes(
                $postalCodes,
                $requestedDate,
                $environment
            );

            return $resultJson->setData([
                'success' => true,
                'data' => $resultData,
            ]);
        } catch (Exception $e) {
            $this->logger->error('[GetPickupHours Controller] Error: ' . $e->getMessage());

            return $resultJson->setData([
                'success' => false,
                'message' => __('Failed to load pickup hours: %1', $e->getMessage()),
            ]);
        }
    }
}
