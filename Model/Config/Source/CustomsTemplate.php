<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Config\Source;

use DhlPoland\Dhl\Model\ResourceModel\CustomsTemplate\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class CustomsTemplate implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Return Customs Clearance Templates option array.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->collectionFactory->create();
        $collection->setOrder('template_name', 'ASC');

        foreach ($collection as $item) {
            $nameStr = (string)($item->getTemplateName() ?? '');
            $options[] = [
                'value' => (string)$item->getEntityId(),
                'label' => $nameStr,
            ];
        }

        return $options;
    }
}
