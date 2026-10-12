<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\ResourceModel\DropOffPoint;

use DhlPoland\Dhl\Model\DropOffPoint as DropOffPointModel;
use DhlPoland\Dhl\Model\ResourceModel\DropOffPoint as DropOffPointResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Initialize collection model and resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(DropOffPointModel::class, DropOffPointResource::class);
    }
}
