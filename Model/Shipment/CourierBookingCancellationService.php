<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Shipment;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\Api\CancelCourierBookingResult;
use Ideo\Dhl\Model\Api\ClientInterface;
use Ideo\Dhl\Model\Api\CredentialsProvider;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory as ShipmentDataCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class CourierBookingCancellationService
{
    /**
     * @param ClientInterface $client
     * @param CredentialsProvider $credentialsProvider
     * @param ShipmentDataRepositoryInterface $shipmentDataRepository
     * @param ShipmentDataCollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly CredentialsProvider $credentialsProvider,
        private readonly ShipmentDataRepositoryInterface $shipmentDataRepository,
        private readonly ShipmentDataCollectionFactory $collectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cancel courier booking for selected shipment entity IDs or dispatch numbers.
     *
     * @param array $ids Array of entity IDs (int) or dispatch numbers (string)
     * @param string $environment ('sandbox' or 'production')
     *
     * @return CancelCourierBookingResult
     * @throws LocalizedException
     */
    public function cancelCourierBooking(
        array $ids,
        string $environment = 'sandbox'
    ): CancelCourierBookingResult {
        if (empty($ids)) {
            throw new LocalizedException(__('No shipments or courier orders selected for cancellation.'));
        }

        $dispatchNumbers = [];
        $affectedEntities = [];

        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                try {
                    $entity = $this->shipmentDataRepository->getById((int) $id);
                    $dispatchNum = $entity->getDispatchNumber();
                    if (empty($dispatchNum) && preg_match('/\(([A-Za-z0-9]+)\)/', $entity->getStatus(), $matches)) {
                        $dispatchNum = $matches[1];
                    }
                    if (empty($dispatchNum) && !empty($entity->getShipmentId())) {
                        $dispatchNum = $entity->getShipmentId();
                    }
                    if (!empty($dispatchNum)) {
                        $dispatchNumbers[] = (string) $dispatchNum;
                        $affectedEntities[(int) $id] = $entity;
                    }
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[CourierBookingCancellationService] Could not load shipment entity #%s: %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            } elseif (is_string($id) && trim($id) !== '') {
                $dispatchNumbers[] = trim($id);
            }
        }

        $dispatchNumbers = array_values(array_unique($dispatchNumbers));

        if (empty($dispatchNumbers)) {
            throw new LocalizedException(
                __('None of the selected shipments have an active courier booking/dispatch number to cancel.')
            );
        }

        $credentials = $this->credentialsProvider->getCredentials($environment);

        $result = $this->client->cancelCourierBooking($credentials, $dispatchNumbers);

        if ($result->isSuccess()) {
            $resetStatus = (string) __('Nowa');
            $entitiesToUpdate = [];

            if ($this->collectionFactory !== null) {
                try {
                    $collection = $this->collectionFactory->create();
                    $collection->addFieldToFilter(ShipmentDataInterface::DISPATCH_NUMBER, ['in' => $dispatchNumbers]);
                    foreach ($collection as $entity) {
                        $entitiesToUpdate[(int) $entity->getEntityId()] = $entity;
                    }
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[CourierBookingCancellationService] Failed to query entities by dispatch number: %s',
                        $e->getMessage()
                    ));
                }
            }

            foreach ($affectedEntities as $id => $entity) {
                $entitiesToUpdate[$id] = $entity;
            }

            foreach ($entitiesToUpdate as $entity) {
                try {
                    $entity->setDispatchNumber(null);
                    $entity->setStatus($resetStatus);
                    $this->shipmentDataRepository->save($entity);
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[CourierBookingCancellationService] Failed to reset dispatch number for entity #%d: %s',
                        $entity->getEntityId(),
                        $e->getMessage()
                    ));
                }
            }
        }

        return $result;
    }
}
