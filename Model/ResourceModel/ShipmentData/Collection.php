<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\ResourceModel\ShipmentData;

use DhlPoland\Dhl\Model\ResourceModel\ShipmentData as ShipmentDataResource;
use DhlPoland\Dhl\Model\ShipmentData as ShipmentDataModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Initialize collection model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ShipmentDataModel::class, ShipmentDataResource::class);
    }
}
