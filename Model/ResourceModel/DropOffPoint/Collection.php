<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel\DropOffPoint;

use Ideo\Dhl\Model\DropOffPoint as DropOffPointModel;
use Ideo\Dhl\Model\ResourceModel\DropOffPoint as DropOffPointResource;
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
