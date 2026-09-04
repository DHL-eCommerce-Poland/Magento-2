<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomsTemplate extends AbstractDb
{
    public const TABLE_NAME = 'dhlparcel_customs_template';

    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }

    /**
     * Process template default flag unicity before saving.
     *
     * @param AbstractModel $object
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeSave(AbstractModel $object): static
    {
        parent::_beforeSave($object);

        if ($object->getData('is_default')) {
            $connection = $this->getConnection();
            $entityId = (int)$object->getId();
            $where = $entityId > 0 ? ['entity_id != ?' => $entityId] : [];

            $connection->update(
                $this->getMainTable(),
                ['is_default' => 0],
                $where
            );
        }

        return $this;
    }
}
