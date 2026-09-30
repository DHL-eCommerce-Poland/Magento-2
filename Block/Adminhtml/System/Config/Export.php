<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;

class Export extends Field
{
    /**
     * @param Context $context
     * @param Escaper $escaper
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Escaper $escaper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render export button HTML element.
     *
     * @param AbstractElement $element
     *
     * @return string
     * @throws LocalizedException
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _getElementHtml(AbstractElement $element): string
    {
        $params = [];
        $website = $this->getRequest()?->getParam('website');
        if ($website !== null) {
            $params['website'] = $website;
        }
        $url = empty($params)
            ? $this->getUrl('dhlparcel/system_config/exportTablerates')
            : $this->getUrl('dhlparcel/system_config/exportTablerates', $params);

        $button = $this->getLayout()->createBlock(Button::class)->setData([
            'id' => 'export_dhl_tablerates_button',
            'label' => __('Export CSV'),
            'onclick' => 'setLocation(\'' . $this->escaper->escapeJs($url) . '\')',
        ]);

        return $button->toHtml();
    }
}
