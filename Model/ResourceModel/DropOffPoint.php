<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DropOffPoint extends AbstractDb
{
    /**
     * Initialize table and primary key.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('ideo_dhl_drop_off_point', 'entity_id');
    }

    /**
     * Perform operations before object save.
     *
     * @param AbstractModel $object
     *
     * @return static
     */
    protected function _beforeSave(AbstractModel $object): static
    {
        if ($object->getData('is_default')) {
            $websiteId = (int)$object->getData('website_id');
            $entityId = (int)$object->getData('entity_id');

            $connection = $this->getConnection();
            $connection->update(
                $this->getMainTable(),
                ['is_default' => 0],
                [
                    'website_id = ?' => $websiteId,
                    'entity_id != ?' => $entityId,
                ]
            );
        }

        return parent::_beforeSave($object);
    }
}
