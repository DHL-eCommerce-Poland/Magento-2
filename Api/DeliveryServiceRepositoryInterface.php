<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Api;

use DhlPoland\Dhl\Api\Data\DeliveryServiceInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface DeliveryServiceRepositoryInterface
{
    /**
     * Save Delivery Service.
     *
     * @param \DhlPoland\Dhl\Api\Data\DeliveryServiceInterface $deliveryService
     *
     * @return \DhlPoland\Dhl\Api\Data\DeliveryServiceInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(DeliveryServiceInterface $deliveryService): DeliveryServiceInterface;

    /**
     * Get Delivery Service by ID.
     *
     * @param int $entityId
     *
     * @return \DhlPoland\Dhl\Api\Data\DeliveryServiceInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): DeliveryServiceInterface;

    /**
     * Get Delivery Service for a given Shipping Method Code.
     *
     * @param string $methodCode
     *
     * @return \DhlPoland\Dhl\Api\Data\DeliveryServiceInterface|null
     */
    public function getByMethod(string $methodCode): ?DeliveryServiceInterface;

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
     * @param \DhlPoland\Dhl\Api\Data\DeliveryServiceInterface $deliveryService
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
