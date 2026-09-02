<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

class TestSandboxConnection extends Field
{
    /**
     * Render button HTML element.
     *
     * @param AbstractElement $element
     *
     * @return string
     * @throws LocalizedException
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $url = $this->getUrl('dhlparcel/system_config/testConnection');

        $button = $this->getLayout()->createBlock(Button::class)->setData([
            'id' => 'dhl_test_connection_sandbox_button',
            'label' => __('Test Connection (Sandbox)'),
            'onclick' => 'javascript:testDhlConnection("sandbox", "' . $url . '"); return false;',
        ]);

        return $button->toHtml();
    }
}
