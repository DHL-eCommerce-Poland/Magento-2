<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

class Export extends Field
{
    /**
     * Render export button HTML element.
     *
     * @param AbstractElement $element
     *
     * @return string
     * @throws LocalizedException
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $url = $this->getUrl('ideo_dhl/system_config/exportTablerates');

        $button = $this->getLayout()->createBlock(Button::class)->setData([
            'id' => 'export_dhl_tablerates_button',
            'label' => __('Export CSV'),
            'onclick' => 'setLocation(\'' . $url . '\')',
        ]);

        return $button->toHtml();
    }
}
