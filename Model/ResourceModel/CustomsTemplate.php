<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomsTemplate extends AbstractDb
{
    public const TABLE_NAME = 'dhlpoland_customs_template';

    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }
}
