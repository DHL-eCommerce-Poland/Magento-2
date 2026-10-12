<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model;

use Exception;
use DhlPoland\Dhl\Api\Data\DeliveryServiceInterface;
use DhlPoland\Dhl\Api\DeliveryServiceRepositoryInterface;
use DhlPoland\Dhl\Model\ResourceModel\DeliveryService as DeliveryServiceResource;
use DhlPoland\Dhl\Model\ResourceModel\DeliveryService\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class DeliveryServiceRepository implements DeliveryServiceRepositoryInterface
{
    /**
     * @param DeliveryServiceResource $resource
     * @param DeliveryServiceFactory $deliveryServiceFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly DeliveryServiceResource $resource,
        private readonly DeliveryServiceFactory $deliveryServiceFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * Save Delivery Service.
     *
     * @param DeliveryServiceInterface $deliveryService
     *
     * @return DeliveryServiceInterface
     * @throws CouldNotSaveException
     */
    public function save(DeliveryServiceInterface $deliveryService): DeliveryServiceInterface
    {
        try {
            /** @var DeliveryService $deliveryService */
            $this->resource->save($deliveryService);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save delivery service: %1', $e->getMessage()), $e);
        }
        return $deliveryService;
    }

    /**
     * Get Delivery Service by ID.
     *
     * @param int $entityId
     *
     * @return DeliveryServiceInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): DeliveryServiceInterface
    {
        $deliveryService = $this->deliveryServiceFactory->create();
        $this->resource->load($deliveryService, $entityId);
        if (!$deliveryService->getEntityId()) {
            throw new NoSuchEntityException(__('Delivery service with ID "%1" does not exist.', $entityId));
        }
        return $deliveryService;
    }

    /**
     * Get Delivery Service for a given Shipping Method Code.
     *
     * @param string $methodCode
     *
     * @return DeliveryServiceInterface|null
     */
    public function getByMethod(string $methodCode): ?DeliveryServiceInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('method_code', ['finset' => $methodCode]);

        $item = $collection->getFirstItem();
        return $item->getId() ? $item : null;
    }

    /**
     * Get List of Delivery Services matching Search Criteria.
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
     * Delete Delivery Service.
     *
     * @param DeliveryServiceInterface $deliveryService
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(DeliveryServiceInterface $deliveryService): bool
    {
        try {
            /** @var DeliveryService $deliveryService */
            $this->resource->delete($deliveryService);
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete delivery service: %1', $e->getMessage()), $e);
        }
        return true;
    }

    /**
     * Delete Delivery Service by ID.
     *
     * @param int $entityId
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }
}
