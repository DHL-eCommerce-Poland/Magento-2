<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Shipment;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Api\ShipmentDataRepositoryInterface;
use DhlPoland\Dhl\Model\Api\CancelCourierBookingResult;
use DhlPoland\Dhl\Model\Api\ClientInterface;
use DhlPoland\Dhl\Model\Api\CredentialsProvider;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory as ShipmentDataCollectionFactory;
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
     *
     * @return CancelCourierBookingResult
     * @throws LocalizedException
     */
    public function cancelCourierBooking(
        array $ids
    ): CancelCourierBookingResult {
        if (empty($ids)) {
            throw new LocalizedException(__('No shipments or courier orders selected for cancellation.'));
        }

        $dispatchNumbers = [];
        $affectedEntitiesByDispatch = [];

        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                try {
                    $entity = $this->shipmentDataRepository->getById((int) $id);
                    $dispatchNum = $entity->getDispatchNumber();
                    if (!empty($dispatchNum)) {
                        $dispatchStr = (string) $dispatchNum;
                        $dispatchNumbers[] = $dispatchStr;
                        $affectedEntitiesByDispatch[$dispatchStr][(int) $id] = $entity;
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
                __('None of the selected shipments have an active courier dispatch number to cancel.')
            );
        }

        $credentials = $this->credentialsProvider->getCredentials();

        $cancelledDispatchNumbers = [];
        $errors = [];

        foreach ($dispatchNumbers as $dispatchNumber) {
            $cancelResult = $this->client->cancelCourierBooking($credentials, $dispatchNumber);
            if ($cancelResult->isSuccess()) {
                $cancelledDispatchNumbers[] = $dispatchNumber;
                $preloaded = $affectedEntitiesByDispatch[$dispatchNumber] ?? [];
                $this->resetEntitiesForDispatchNumber($dispatchNumber, $preloaded);
            } else {
                $errorMsg = $cancelResult->getErrorMessage() ?: (string) __('Failed to cancel courier booking.');
                $errors[$dispatchNumber] = $errorMsg;
                $this->logger->warning(sprintf(
                    '[CourierBookingCancellationService] Failed to cancel courier booking %s: %s',
                    $dispatchNumber,
                    $errorMsg
                ));
            }
        }

        if (empty($errors)) {
            return new CancelCourierBookingResult(
                true,
                implode(', ', $cancelledDispatchNumbers),
                null,
                null,
                $cancelledDispatchNumbers,
                []
            );
        }

        $uniqueErrors = array_unique(array_values($errors));
        $combinedErrorMessage = implode('; ', $uniqueErrors);

        if (empty($cancelledDispatchNumbers)) {
            return new CancelCourierBookingResult(
                false,
                implode(', ', array_keys($errors)),
                'CANCEL_COURIER_ERROR',
                $combinedErrorMessage,
                [],
                $errors
            );
        }

        return new CancelCourierBookingResult(
            false,
            implode(', ', $cancelledDispatchNumbers),
            'PARTIAL_ERROR',
            $combinedErrorMessage,
            $cancelledDispatchNumbers,
            $errors
        );
    }

    /**
     * Reset dispatch number and shipment status for all entities associated with cancelled dispatch number.
     *
     * @param string $dispatchNumber
     * @param ShipmentDataInterface[] $preloadedEntities
     *
     * @return void
     */
    private function resetEntitiesForDispatchNumber(string $dispatchNumber, array $preloadedEntities = []): void
    {
        $resetStatus = (string) __('Nowa');
        $entitiesToUpdate = [];

        try {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter(ShipmentDataInterface::DISPATCH_NUMBER, $dispatchNumber);
            foreach ($collection as $entity) {
                $entitiesToUpdate[(int) $entity->getEntityId()] = $entity;
            }
        } catch (Throwable $e) {
            $this->logger->warning(sprintf(
                '[CourierBookingCancellationService] Failed to query entities by dispatch number %s: %s',
                $dispatchNumber,
                $e->getMessage()
            ));
        }

        foreach ($preloadedEntities as $id => $entity) {
            $entitiesToUpdate[(int) $id] = $entity;
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
}
