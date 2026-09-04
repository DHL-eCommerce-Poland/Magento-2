<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\ResourceModel\CustomsTemplate;

use Ideo\Dhl\Model\CustomsTemplate as CustomsTemplateModel;
use Ideo\Dhl\Model\ResourceModel\CustomsTemplate as CustomsTemplateResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Define resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            CustomsTemplateModel::class,
            CustomsTemplateResource::class
        );
    }
}
