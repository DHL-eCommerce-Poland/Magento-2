<?php

declare(strict_types=1);

namespace Ideo\Dhl\Api;

use Ideo\Dhl\Api\Data\ShipmentDataInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface ShipmentDataRepositoryInterface
{
    /**
     * Save DHL shipment data.
     *
     * @param \Ideo\Dhl\Api\Data\ShipmentDataInterface $shipmentData
     *
     * @return \Ideo\Dhl\Api\Data\ShipmentDataInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(ShipmentDataInterface $shipmentData): ShipmentDataInterface;

    /**
     * Get DHL shipment data by entity ID.
     *
     * @param int $entityId
     *
     * @return \Ideo\Dhl\Api\Data\ShipmentDataInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): ShipmentDataInterface;

    /**
     * Get DHL shipment data by Magento order ID (latest created entity).
     *
     * @param int $orderId
     *
     * @return \Ideo\Dhl\Api\Data\ShipmentDataInterface|null
     */
    public function getByOrderId(int $orderId): ?ShipmentDataInterface;

    /**
     * Get all DHL shipment data entities for a Magento order ID.
     *
     * @param int $orderId
     *
     * @return \Ideo\Dhl\Api\Data\ShipmentDataInterface[]
     */
    public function getListByOrderId(int $orderId): array;

    /**
     * Get list of DHL shipment data entities matching search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete DHL shipment data entity.
     *
     * @param \Ideo\Dhl\Api\Data\ShipmentDataInterface $shipmentData
     *
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(ShipmentDataInterface $shipmentData): bool;

    /**
     * Delete DHL shipment data entity by ID.
     *
     * @param int $entityId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function deleteById(int $entityId): bool;
}
