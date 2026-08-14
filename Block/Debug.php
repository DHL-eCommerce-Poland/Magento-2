<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class Debug extends Template
{
    private const XML_PATH_DEBUG = 'carriers/dhlparcel/debug';

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if DHL debug mode is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG,
            ScopeInterface::SCOPE_STORE
        );
    }
}
