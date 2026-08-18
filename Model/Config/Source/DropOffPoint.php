<?php

declare(strict_types=1);

namespace Ideo\Dhl\Model\Config\Source;

use Ideo\Dhl\Model\ResourceModel\DropOffPoint\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class DropOffPoint implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Return Drop-Off Points option array with ID in parentheses.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->collectionFactory->create();
        $collection->setOrder('template_name', 'ASC');

        foreach ($collection as $item) {
            $name = $item->getTemplateName();
            $id = $item->getEntityId();
            $label = $name ? sprintf('%s (ID: %d)', $name, $id) : sprintf('ID: %d', $id);

            $options[] = [
                'value' => (string)$id,
                'label' => $label,
            ];
        }

        return $options;
    }
}
