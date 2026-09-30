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
            $confirmMessage = $this->escapeJs((string) __('Are you sure you want to delete this record?'));
            $deleteUrl = $this->escapeJs($this->getUrl('*/*/delete', ['entity_id' => $entityId]));
            $data = [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => sprintf(
                    "deleteConfirm('%s', '%s', {data: {}})",
                    $confirmMessage,
                    $deleteUrl
                ),
                'sort_order' => 20,
            ];
        }
        return $data;
    }
}
