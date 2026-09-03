<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\Button;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Escaper;

class Generic
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     */
    public function __construct(
        protected Context $context,
        protected readonly Escaper $escaper
    ) {
    }

    /**
     * Get entity ID.
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        $id = $this->context->getRequest()->getParam('entity_id');
        return $id ? (int)$id : null;
    }

    /**
     * Generate URL by route and parameters.
     *
     * @param string $route
     * @param array $params
     *
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
