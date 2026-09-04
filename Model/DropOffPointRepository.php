<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model;

use Exception;
use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Ideo\Dhl\Api\DropOffPointRepositoryInterface;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint as DropOffPointResource;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class DropOffPointRepository implements DropOffPointRepositoryInterface
{
    /**
     * @param DropOffPointResource $resource
     * @param DropOffPointFactory $dropOffPointFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly DropOffPointResource $resource,
        private readonly DropOffPointFactory $dropOffPointFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * Save Drop-Off Point.
     *
     * @param DropOffPointInterface $dropOffPoint
     *
     * @return DropOffPointInterface
     * @throws CouldNotSaveException
     */
    public function save(DropOffPointInterface $dropOffPoint): DropOffPointInterface
    {
        try {
            /** @var DropOffPoint $dropOffPoint */
            $this->resource->save($dropOffPoint);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save drop-off point: %1', $e->getMessage()), $e);
        }
        return $dropOffPoint;
    }

    /**
     * Get Drop-Off Point by ID.
     *
     * @param int $entityId
     *
     * @return DropOffPointInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): DropOffPointInterface
    {
        $dropOffPoint = $this->dropOffPointFactory->create();
        $this->resource->load($dropOffPoint, $entityId);
        if (!$dropOffPoint->getEntityId()) {
            throw new NoSuchEntityException(__('Drop-off point with ID "%1" does not exist.', $entityId));
        }
        return $dropOffPoint;
    }

    /**
     * Get default Drop-Off Point.
     *
     * @return DropOffPointInterface|null
     */
    public function getDefault(): ?DropOffPointInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_default', 1);

        $item = $collection->getFirstItem();
        return $item->getId() ? $item : null;
    }

    /**
     * Get List of Drop-Off Points matching Search Criteria.
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
     * Delete Drop-Off Point.
     *
     * @param DropOffPointInterface $dropOffPoint
     *
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(DropOffPointInterface $dropOffPoint): bool
    {
        try {
            /** @var DropOffPoint $dropOffPoint */
            $this->resource->delete($dropOffPoint);
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete drop-off point: %1', $e->getMessage()), $e);
        }
        return true;
    }

    /**
     * Delete Drop-Off Point by ID.
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
