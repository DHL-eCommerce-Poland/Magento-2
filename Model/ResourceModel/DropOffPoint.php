<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
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
        $this->_init('dhlpoland_drop_off_point_template', 'entity_id');
    }

    /**
     * Perform operations before object save.
     *
     * @param AbstractModel $object
     *
     * @return static
     * @throws LocalizedException
     */
    protected function _beforeSave(AbstractModel $object): static
    {
        if ($object->getData('is_default')) {
            $entityId = (int)$object->getData('entity_id');

            $connection = $this->getConnection();
            $connection->update(
                $this->getMainTable(),
                ['is_default' => 0],
                [
                    'entity_id != ?' => $entityId,
                ]
            );
        }

        return parent::_beforeSave($object);
    }
}
