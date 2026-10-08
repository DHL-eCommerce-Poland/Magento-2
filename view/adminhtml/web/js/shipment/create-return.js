define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/alert',
    'Ideo_Dhl/js/utils/safe-message-content',
    'mage/translate',
    'mage/loader'
], function ($, confirm, alert, createSafeMessageContent, $t) {
    'use strict';

    /**
     * Confirm and dispatch return shipment creation via AJAX.
     * Displays errors in an alert modal without reloading or losing form data.
     *
     * @param {string} url
     * @param {string} [confirmMessage]
     */
    return function (url, confirmMessage) {
        if (!url) {
            return;
        }

        confirm({
            title: $t('Create Return Shipment'),
            content: confirmMessage || $t('Are you sure you want to create a return shipment for this parcel?'),
            actions: {
                confirm: function () {
                    if (typeof $('body').loader === 'function') {
                        $('body').loader();
                    }
                    $('body').trigger('processStart');

                    $.post(url, { isAjax: true })
                        .done(function (response) {
                            if (response && response.success) {
                                window.location.reload();
                            } else {
                                $('body').trigger('processStop');
                                alert({
                                    title: $t('Error'),
                                    content: createSafeMessageContent(
                                        (response && response.message)
                                            ? response.message
                                            : $t('Failed to create return shipment.')
                                    )
                                });
                            }
                        })
                        .fail(function () {
                            $('body').trigger('processStop');
                            alert({
                                title: $t('Error'),
                                content: $t('An error occurred while communicating with the server.')
                            });
                        });
                }
            }
        });
    };
});
