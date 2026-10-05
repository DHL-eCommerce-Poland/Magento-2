<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\CourierBookingService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class BookCourier extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param CourierBookingService $courierBookingService
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CourierBookingService $courierBookingService,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Execute courier booking request for selected DHL shipments.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();

        try {
            $shipmentIds = (array) $this->getRequest()->getParam('shipment_ids', []);
            $pickupDate = (string) $this->getRequest()->getParam('pickup_date');
            $pickupTimeFrom = (string) $this->getRequest()->getParam('pickup_time_from');
            $pickupTimeTo = (string) $this->getRequest()->getParam('pickup_time_to');
            $additionalInfo = (string) $this->getRequest()->getParam('additional_info');

            if (empty($shipmentIds)) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => (string) __('Please select at least one shipment for courier booking.'),
                ]);
            }

            if (empty($pickupDate) || empty($pickupTimeFrom) || empty($pickupTimeTo)) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => (string) __('Pickup date and time window (From - To) must be specified.'),
                ]);
            }

            $environment = (string) $this->scopeConfig->getValue('carriers/dhlpoland/environment') ?: 'sandbox';

            $bookingResult = $this->courierBookingService->bookCourier(
                $shipmentIds,
                $pickupDate,
                $pickupTimeFrom,
                $pickupTimeTo,
                $additionalInfo,
                $environment
            );

            if ($bookingResult->isSuccess()) {
                $orderMsg = $bookingResult->getCourierOrderId()
                    ? __(
                        'Courier booked successfully. Courier order number: %1',
                        $bookingResult->getCourierOrderId()
                    )
                    : __('Courier booked successfully.');

                $this->messageManager->addSuccessMessage($orderMsg);

                return $resultJson->setData([
                    'success' => true,
                    'message' => (string) $orderMsg,
                    'courier_order_id' => $bookingResult->getCourierOrderId(),
                ]);
            }

            $errMsg = $bookingResult->getErrorMessage()
                ?: (string) __('Failed to book courier via DHL WebAPI2.');

            return $resultJson->setData([
                'success' => false,
                'message' => $errMsg,
            ]);
        } catch (Exception $e) {
            $this->logger->error('[BookCourier Controller] Error: ' . $e->getMessage());

            return $resultJson->setData([
                'success' => false,
                'message' => __('Error while booking courier: %1', $e->getMessage()),
            ]);
        }
    }
}
