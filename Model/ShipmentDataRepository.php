<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Exception;
use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Ideo\Dhl\Api\ShipmentDataRepositoryInterface;
use Ideo\Dhl\Model\ResourceModel\ShipmentData as ShipmentDataResource;
use Ideo\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory as ShipmentDataCollectionFactory;
use Magento\Framework\Api\CriteriaInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ShipmentDataRepository implements ShipmentDataRepositoryInterface
{
    /**
     * @param ShipmentDataResource $resource
     * @param ShipmentDataFactory $shipmentDataFactory
     * @param ShipmentDataCollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly ShipmentDataResource $resource,
        private readonly ShipmentDataFactory $shipmentDataFactory,
        private readonly ShipmentDataCollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * Save DHL shipment data.
     *
     * @param ShipmentDataInterface $shipmentData
     *
     * @return ShipmentDataInterface
     * @throws CouldNotSaveException
     */
    public function save(ShipmentDataInterface $shipmentData): ShipmentDataInterface
    {
        try {
            /** @var ShipmentData $shipmentData */
            $this->resource->save($shipmentData);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save DHL shipment data: %1', $exception->getMessage()),
                $exception
            );
        }
        return $shipmentData;
    }

    /**
     * Get DHL shipment data by entity ID.
     *
     * @param int $entityId
     *
     * @return ShipmentDataInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): ShipmentDataInterface
    {
        $entity = $this->shipmentDataFactory->create();
        $this->resource->load($entity, $entityId);
        if (!$entity->getEntityId()) {
            throw new NoSuchEntityException(__('DHL shipment data with ID "%1" does not exist.', $entityId));
        }
        return $entity;
    }

    /**
     * Get DHL shipment data by Magento order ID (latest created entity).
     *
     * @param int $orderId
     *
     * @return ShipmentDataInterface|null
     */
    public function getByOrderId(int $orderId): ?ShipmentDataInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(ShipmentDataInterface::ORDER_ID, $orderId);
        $collection->setOrder(ShipmentDataInterface::ENTITY_ID, CriteriaInterface::SORT_ORDER_DESC);
        /** @var ShipmentData|null $entity */
        $entity = $collection->getFirstItem();
        return ($entity && $entity->getEntityId()) ? $entity : null;
    }

    /**
     * Get all DHL shipment data entities for a Magento order ID.
     *
     * @param int $orderId
     *
     * @return ShipmentDataInterface[]
     */
    public function getListByOrderId(int $orderId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(ShipmentDataInterface::ORDER_ID, $orderId);
        $collection->setOrder(ShipmentDataInterface::ENTITY_ID, CriteriaInterface::SORT_ORDER_DESC);
        return array_values($collection->getItems());
    }

    /**
     * Get list of DHL shipment data entities matching Search Criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * Delete DHL shipment data entity.
     *
     * @param ShipmentDataInterface $shipmentData
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ShipmentDataInterface $shipmentData): bool
    {
        try {
            /** @var ShipmentData $shipmentData */
            $this->resource->delete($shipmentData);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete DHL shipment data: %1', $exception->getMessage()),
                $exception
            );
        }
        return true;
    }

    /**
     * Delete DHL shipment data entity by ID.
     *
     * @param int $entityId
     *
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }
}
