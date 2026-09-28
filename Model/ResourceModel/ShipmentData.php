<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ShipmentData extends AbstractDb
{
    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('dhlpoland_shipment', 'entity_id');
    }
}
