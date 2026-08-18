<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api;

use Ideo\Dhl\Api\Data\DeliveryServiceInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface DeliveryServiceRepositoryInterface
{
    /**
     * Save Delivery Service.
     *
     * @param \Ideo\Dhl\Api\Data\DeliveryServiceInterface $deliveryService
     *
     * @return \Ideo\Dhl\Api\Data\DeliveryServiceInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(DeliveryServiceInterface $deliveryService): DeliveryServiceInterface;

    /**
     * Get Delivery Service by ID.
     *
     * @param int $entityId
     *
     * @return \Ideo\Dhl\Api\Data\DeliveryServiceInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): DeliveryServiceInterface;

    /**
     * Get Delivery Service for a given Website ID and Shipping Method Code.
     *
     * @param int $websiteId
     * @param string $methodCode
     *
     * @return \Ideo\Dhl\Api\Data\DeliveryServiceInterface|null
     */
    public function getByWebsiteAndMethod(int $websiteId, string $methodCode): ?DeliveryServiceInterface;

    /**
     * Get List of Delivery Services matching Search Criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete Delivery Service.
     *
     * @param \Ideo\Dhl\Api\Data\DeliveryServiceInterface $deliveryService
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(DeliveryServiceInterface $deliveryService): bool;

    /**
     * Delete Delivery Service by ID.
     *
     * @param int $entityId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById(int $entityId): bool;
}
