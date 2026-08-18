<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel\DeliveryService;

use Ideo\Dhl\Model\DeliveryService as DeliveryServiceModel;
use Ideo\Dhl\Model\ResourceModel\DeliveryService as DeliveryServiceResource;
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
        $this->_init(DeliveryServiceModel::class, DeliveryServiceResource::class);
    }
}
