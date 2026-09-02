<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DeliveryService extends AbstractDb
{
    /**
     * Initialize table and primary key.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('dhlparcel_delivery_service', 'entity_id');
    }
}
