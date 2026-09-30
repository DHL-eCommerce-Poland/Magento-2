<?php

declare(strict_types=1);

namespace Ideo\Dhl\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;

class TestProductionConnection extends Field
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
            'id' => 'dhl_test_connection_production_button',
            'label' => __('Test Connection (Production)'),
            'onclick' => 'javascript:testDhlConnection("production", "'
                . $this->escaper->escapeJs($url)
                . '"); return false;',
        ]);

        $titleSuccess = json_encode((string) __('Success'));
        $titleConnectionError = json_encode((string) __('Connection Error'));
        $titleError = json_encode((string) __('Error'));
        $contentError = json_encode((string) __('An error occurred while testing connection to DHL.'));

        $html = $button->toHtml();

        $html .= <<<HTML
<script type="text/javascript">
require(['jquery', 'Magento_Ui/js/modal/alert'], function($, alert) {
    window.testDhlConnection = function(env, url) {
        var loginInput = $('#carriers_dhlparcel_general_api_login').val();
        var passwordInput = $('#carriers_dhlparcel_general_api_password').val();

        if (env === 'sandbox') {
            loginInput = $('#carriers_dhlparcel_general_api_login_sandbox').val();
            passwordInput = $('#carriers_dhlparcel_general_api_password_sandbox').val();
        }

        var postData = {
            form_key: FORM_KEY,
            environment: env
        };

        if (env === 'sandbox') {
            postData.api_login_sandbox = loginInput;
            postData.api_password_sandbox = passwordInput;
        } else {
            postData.api_login = loginInput;
            postData.api_password = passwordInput;
        }

        $('body').trigger('processStart');

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: postData,
            success: function(response) {
                $('body').trigger('processStop');
                var content = $('<div>').text(response.message || '').html();
                if (response.success) {
                    alert({
                        title: {$titleSuccess},
                        content: content
                    });
                } else {
                    alert({
                        title: {$titleConnectionError},
                        content: content
                    });
                }
            },
            error: function() {
                $('body').trigger('processStop');
                alert({
                    title: {$titleError},
                    content: {$contentError}
                });
            }
        });
    };
});
</script>
HTML;

        return $html;
    }
}
