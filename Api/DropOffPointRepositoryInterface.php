<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api;

use Ideo\Dhl\Api\Data\DropOffPointInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface DropOffPointRepositoryInterface
{
    /**
     * Save Drop-Off Point.
     *
     * @param \Ideo\Dhl\Api\Data\DropOffPointInterface $dropOffPoint
     *
     * @return \Ideo\Dhl\Api\Data\DropOffPointInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(DropOffPointInterface $dropOffPoint): DropOffPointInterface;

    /**
     * Get Drop-Off Point by ID.
     *
     * @param int $entityId
     *
     * @return \Ideo\Dhl\Api\Data\DropOffPointInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): DropOffPointInterface;

    /**
     * Get default Drop-Off Point for a given Website ID (with fallback to default website 0).
     *
     * @param int $websiteId
     *
     * @return \Ideo\Dhl\Api\Data\DropOffPointInterface|null
     */
    public function getDefaultByWebsiteId(int $websiteId = 0): ?DropOffPointInterface;

    /**
     * Get List of Drop-Off Points matching Search Criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete Drop-Off Point.
     *
     * @param \Ideo\Dhl\Api\Data\DropOffPointInterface $dropOffPoint
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(DropOffPointInterface $dropOffPoint): bool;

    /**
     * Delete Drop-Off Point by ID.
     *
     * @param int $entityId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById(int $entityId): bool;
}
