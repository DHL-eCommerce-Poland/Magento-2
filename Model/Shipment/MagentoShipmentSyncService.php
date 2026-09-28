<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Ideo\Dhl\Model\Shipment;

use Exception;
use Ideo\Dhl\Model\Carrier;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Sender\EmailSender;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentFactory;
use Psr\Log\LoggerInterface;

class MagentoShipmentSyncService
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentFactory $shipmentFactory
     * @param TrackFactory $trackFactory
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ShipmentTrackRepositoryInterface $trackRepository
     * @param EmailSender $emailSender
     * @param TransactionFactory $transactionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentFactory $shipmentFactory,
        private readonly TrackFactory $trackFactory,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly ShipmentTrackRepositoryInterface $trackRepository,
        private readonly EmailSender $emailSender,
        private readonly TransactionFactory $transactionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create native Magento shipment or attach tracking numbers to target/existing shipment.
     *
     * @param int $orderId
     * @param array $trackingNumbers
     * @param array $itemsQty Quantities per order_item_id [order_item_id => qty]. Empty array ships all available.
     * @param bool $sendEmail Whether to send shipment notification email.
     * @param int|null $targetShipmentId Specific sales_shipment entity ID to attach tracking to if order is already shipped.
     *
     * @return ShipmentInterface
     * @throws LocalizedException
     */
    public function createMagentoShipment(
        int $orderId,
        array $trackingNumbers,
        array $itemsQty = [],
        bool $sendEmail = true,
        ?int $targetShipmentId = null
    ): ShipmentInterface {
        $order = $this->orderRepository->get($orderId);

        // Fallback / Attachment for already shipped orders (e.g. recreating or generating new label for existing shipment)
        if (!$order->canShip()) {
            if ($targetShipmentId !== null) {
                try {
                    $targetShipment = $this->shipmentRepository->get($targetShipmentId);
                    $this->appendTracksToShipment($targetShipment, $trackingNumbers);
                    $savedTarget = $this->shipmentRepository->save($targetShipment);
                    if ($order instanceof Order) {
                        $this->addShipmentCreatedOrderComment($order, $savedTarget, $trackingNumbers);
                    }
                    return $savedTarget;
                } catch (Exception $e) {
                    $this->logger->warning(sprintf(
                        '[DHL] Target shipment #%d not found for order #%d, falling back to last shipment: %s',
                        $targetShipmentId,
                        $orderId,
                        $e->getMessage()
                    ));
                }
            }

            if ($order instanceof Order) {
                $shipmentsCollection = $order->getShipmentsCollection();
                if ($shipmentsCollection->getSize() > 0) {
                    /** @var Shipment $existingShipment */
                    $existingShipment = $shipmentsCollection->getLastItem();
                    $this->appendTracksToShipment($existingShipment, $trackingNumbers);
                    $savedExisting = $this->shipmentRepository->save($existingShipment);
                    $this->addShipmentCreatedOrderComment($order, $savedExisting, $trackingNumbers);
                    return $savedExisting;
                }
            }

            throw new LocalizedException(__('Cannot create or find Magento shipment for order #%1.', $order->getIncrementId()));
        }

        if (empty($itemsQty)) {
            foreach ($order->getAllItems() as $orderItem) {
                if (method_exists($orderItem, 'isDummy') && $orderItem->isDummy()) {
                    continue;
                }
                if ($orderItem->getQtyToShip() > 0) {
                    $itemsQty[$orderItem->getId()] = $orderItem->getQtyToShip();
                }
            }
        }

        $shipment = $this->shipmentFactory->create($order, $itemsQty);
        $shipment->register();

        $this->appendTracksToShipment($shipment, $trackingNumbers);

        $transaction = $this->transactionFactory->create();
        $transaction->addObject($shipment);
        if ($order instanceof Order) {
            $order->setIsInProcess(true);
            $transaction->addObject($order);
        }
        $transaction->save();

        if ($order instanceof Order) {
            $this->addShipmentCreatedOrderComment($order, $shipment, $trackingNumbers);
        }

        if ($sendEmail) {
            try {
                $this->emailSender->send($order, $shipment);
            } catch (Exception $e) {
                $this->logger->error(sprintf(
                    '[DHL] Failed to send shipment email for shipment #%s: %s',
                    $shipment->getIncrementId(),
                    $e->getMessage()
                ));
            }
        }

        return $shipment;
    }

    /**
     * Add order status history comment when DHL shipment or tracking is created.
     *
     * @param Order $order
     * @param ShipmentInterface $shipment
     * @param array $trackingNumbers
     *
     * @return void
     */
    private function addShipmentCreatedOrderComment(Order $order, ShipmentInterface $shipment, array $trackingNumbers): void
    {
        try {
            $incrementId = method_exists($shipment, 'getIncrementId') && $shipment->getIncrementId()
                ? $shipment->getIncrementId()
                : $shipment->getEntityId();
            $trackingStr = !empty($trackingNumbers) ? implode(', ', $trackingNumbers) : 'N/A';

            $order->addCommentToStatusHistory(
                __('DHL shipment #%1 created with tracking number(s): %2.', $incrementId, $trackingStr)
            );
            $this->orderRepository->save($order);
        } catch (Exception $e) {
            $this->logger->warning(sprintf(
                '[DHL] Failed to add order history comment for created shipment: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Update tracking numbers on existing Magento shipment.
     *
     * Removes old tracks via TrackRepository and clears memory collection.
     *
     * @param int $magentoShipmentId
     * @param array $newTrackingNumbers
     *
     * @return void
     * @throws LocalizedException
     */
    public function updateMagentoShipmentTracks(int $magentoShipmentId, array $newTrackingNumbers): void
    {
        $shipment = $this->shipmentRepository->get($magentoShipmentId);

        // Physically delete old DHL tracks from database via TrackRepository & remove from memory collection
        foreach ($shipment->getAllTracks() as $track) {
            if ($track->getCarrierCode() === Carrier::CODE) {
                try {
                    $this->trackRepository->delete($track);
                    if ($shipment instanceof Shipment && method_exists($shipment, 'removeTrack')) {
                        $shipment->removeTrack($track->getEntityId());
                    }
                } catch (Exception $e) {
                    $this->logger->warning(sprintf(
                        '[DHL] Failed to delete track #%s from database: %s',
                        $track->getEntityId(),
                        $e->getMessage()
                    ));
                }
            }
        }

        $this->appendTracksToShipment($shipment, $newTrackingNumbers);
        $this->shipmentRepository->save($shipment);
    }

    /**
     * Cancel and remove DHL tracking numbers from Magento shipment without deleting shipment or altering order state.
     *
     * @param int $magentoShipmentId
     *
     * @return void
     * @throws LocalizedException
     */
    public function cancelDhlTracking(int $magentoShipmentId): void
    {
        $shipment = $this->shipmentRepository->get($magentoShipmentId);
        $tracksRemoved = false;

        foreach ($shipment->getAllTracks() as $track) {
            if ($track->getCarrierCode() === Carrier::CODE) {
                try {
                    $this->trackRepository->delete($track);
                    if ($shipment instanceof Shipment && method_exists($shipment, 'removeTrack')) {
                        $shipment->removeTrack($track->getEntityId());
                    }
                    $tracksRemoved = true;
                } catch (Exception $e) {
                    $this->logger->warning(sprintf(
                        '[DHL] Failed to delete track #%s from database: %s',
                        $track->getEntityId(),
                        $e->getMessage()
                    ));
                }
            }
        }

        if ($tracksRemoved) {
            try {
                $order = $this->orderRepository->get((int)$shipment->getOrderId());
                if ($order instanceof Order) {
                    $incrementId = method_exists($shipment, 'getIncrementId') ? $shipment->getIncrementId() : $magentoShipmentId;
                    $order->addCommentToStatusHistory(
                        __('DHL tracking for shipment #%1 has been canceled.', $incrementId)
                    );
                    $this->orderRepository->save($order);
                }
            } catch (Exception $e) {
                $this->logger->warning(sprintf(
                    '[DHL] Failed to add order history comment for canceled shipment #%d: %s',
                    $magentoShipmentId,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Helper method to append tracking numbers to a shipment object.
     *
     * @param ShipmentInterface $shipment
     * @param array $trackingNumbers
     *
     * @return void
     */
    private function appendTracksToShipment(ShipmentInterface $shipment, array $trackingNumbers): void
    {
        foreach ($trackingNumbers as $number) {
            $track = $this->trackFactory->create();
            $track->setCarrierCode(Carrier::CODE);
            $track->setTitle('DHL eCommerce Poland');
            $track->setNumber((string)$number);
            $shipment->addTrack($track);
        }
    }
}
