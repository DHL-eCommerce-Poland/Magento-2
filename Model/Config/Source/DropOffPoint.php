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
            $nameStr = $item->getTemplateName() ?: $item->getSenderName();
            $label = sprintf('%s (%s, %s)', $nameStr, $item->getCity(), $item->getStreet());
            if ($item->getIsDefault()) {
                $label .= ' *';
            }

            $options[] = [
                'value' => (string)$item->getEntityId(),
                'label' => $label,
            ];
        }

        return $options;
    }
}
