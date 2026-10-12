<?php

declare(strict_types=1);

namespace DhlPoland\Dhl\Model\Config\Source;

use DhlPoland\Dhl\Model\MethodCatalog;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\ScopeInterface;

class ShippingMethod implements OptionSourceInterface
{
    /**
     * @param MethodCatalog $methodCatalog
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly MethodCatalog $methodCatalog,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Return DHL shipping methods option array dynamically loaded from MethodCatalog and Store Config.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->methodCatalog->getMethods() as $method) {
            $code = $method['code'];
            $configuredTitle = $this->scopeConfig->getValue(
                'carriers/dhlpoland/method_' . $code . '_method_name',
                ScopeInterface::SCOPE_STORE
            ) ?: $this->scopeConfig->getValue(
                'carriers/dhlpoland/method_' . $code . '_title',
                ScopeInterface::SCOPE_STORE
            );

            $title = $configuredTitle ? (string)$configuredTitle : $method['title'];

            $options[] = [
                'value' => $code,
                'label' => __('%1 [%2]', $title, $code),
            ];
        }

        return $options;
    }
}
