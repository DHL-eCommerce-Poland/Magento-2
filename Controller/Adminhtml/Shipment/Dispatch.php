<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\ShipmentCreationService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class Dispatch extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param ShipmentCreationService $shipmentCreationService
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly ShipmentCreationService $shipmentCreationService,
        private readonly JsonFactory $jsonFactory,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute dispatch shipment action.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();
        $entityId = (int) $this->getRequest()->getParam('entity_id');
        $environment = (string) ($this->getRequest()->getParam('environment') ?? 'sandbox');

        if ($entityId <= 0) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid or missing shipment entity ID.')->render(),
            ]);
        }

        try {
            $result = $this->shipmentCreationService->dispatchShipment($entityId, $environment);

            if ($result->isTimeout()) {
                return $resultJson->setData([
                    'success' => false,
                    'isTimeout' => true,
                    'message' => __(
                        'Connection to DHL timed out. Status set to PENDING_VERIFICATION. '
                        . 'Please verify parcel status in DHL24 portal before re-trying.'
                    )->render(),
                ]);
            }

            if (!$result->isSuccess()) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => $result->getErrorMessage() ?? __('Failed to create shipment in DHL.')->render(),
                    'errorCode' => $result->getErrorCode(),
                ]);
            }

            return $resultJson->setData([
                'success' => true,
                'shipmentId' => $result->getShipmentId(),
                'message' => __(
                    'DHL shipment created successfully. Waybill #: %1',
                    $result->getShipmentId()
                )->render(),
            ]);
        } catch (Exception $e) {
            $this->logger?->error('Error dispatching DHL shipment: ' . $e->getMessage(), ['exception' => $e]);

            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
