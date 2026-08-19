<?php

declare(strict_types=1);

namespace Ideo\Dhl\Controller\Adminhtml\Shipment;

use Exception;
use Ideo\Dhl\Model\Shipment\ShipmentDataService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassCreate extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Ideo_Dhl::shipment';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ShipmentDataService $shipmentDataService
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ShipmentDataService $shipmentDataService,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct($context);
    }

    /**
     * Execute mass create shipment action.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $orderIds = $collection->getAllIds();

            if (empty($orderIds)) {
                $this->messageManager->addErrorMessage(__('Please select order(s) to create DHL shipments.'));
            } else {
                $result = $this->shipmentDataService->prepareAndSaveMassShipmentData($orderIds);
                if ($result['created'] > 0) {
                    $this->messageManager->addSuccessMessage(
                        __('Successfully created %1 DHL shipment(s). Ready for courier booking.', $result['created'])
                    );
                }
                if ($result['errors'] > 0) {
                    $this->messageManager->addErrorMessage(
                        __('Failed to create %1 DHL shipment(s).', $result['errors'])
                    );
                }
            }
        } catch (Exception $e) {
            $this->logger?->error(
                'Error creating DHL shipments in mass action: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(__('Error creating DHL shipments: %1', $e->getMessage()));
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/order/index');
    }
}
