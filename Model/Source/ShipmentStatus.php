<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Source;

use DhlPoland\Dhl\Api\Data\ShipmentDataInterface;
use DhlPoland\Dhl\Model\ResourceModel\ShipmentData\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;
use Throwable;

class ShipmentStatus implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Get options array for UI Components.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [
            ['value' => ShipmentDataInterface::STATUS_NEW, 'label' => __('New')],
            ['value' => ShipmentDataInterface::STATUS_COURIER_BOOKED, 'label' => __('Courier Booked')],
        ];

        foreach ($this->getDistinctTrackingStatuses() as $status) {
            $options[] = [
                'value' => $status,
                'label' => __($status),
            ];
        }

        return $options;
    }

    /**
     * Get options as associative array [value => label].
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = [
            ShipmentDataInterface::STATUS_NEW => __('New'),
            ShipmentDataInterface::STATUS_COURIER_BOOKED => __('Courier Booked'),
        ];

        foreach ($this->getDistinctTrackingStatuses() as $status) {
            $array[$status] = __($status);
        }

        return $array;
    }

    /**
     * Retrieve distinct custom or tracking statuses present in the database.
     *
     * @return string[]
     */
    private function getDistinctTrackingStatuses(): array
    {
        try {
            $collection = $this->collectionFactory->create();
            $connection = $collection->getConnection();
            $select = $connection->select()
                ->from($collection->getMainTable(), [ShipmentDataInterface::STATUS])
                ->distinct(true)
                ->where(
                    ShipmentDataInterface::STATUS . ' NOT IN (?)',
                    [
                        ShipmentDataInterface::STATUS_NEW,
                        ShipmentDataInterface::STATUS_COURIER_BOOKED,
                    ]
                )
                ->where(ShipmentDataInterface::STATUS . ' IS NOT NULL')
                ->where(ShipmentDataInterface::STATUS . ' != ""');

            $statuses = $connection->fetchCol($select);

            return is_array($statuses) ? array_values(array_filter($statuses)) : [];
        } catch (Throwable) {
            return [];
        }
    }
}
