<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class Delete extends Generic implements ButtonProviderInterface
{
    /**
     * Get button data.
     *
     * @return array
     */
    public function getButtonData(): array
    {
        $data = [];
        $entityId = $this->getEntityId();
        if ($entityId) {
            $data = [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => sprintf(
                    "deleteConfirm('%s', '%s')",
                    __('Are you sure you want to delete this record?'),
                    $this->getUrl('*/*/delete', ['entity_id' => $entityId])
                ),
                'sort_order' => 20,
            ];
        }
        return $data;
    }
}
