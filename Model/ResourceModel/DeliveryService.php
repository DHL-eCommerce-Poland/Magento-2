<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\ResourceModel;

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
        $this->_init('dhlpoland_delivery_service_template', 'entity_id');
    }
}
