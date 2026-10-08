define([
    'jquery',
    'uiRegistry',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/alert',
    'Ideo_Dhl/js/utils/safe-message-content',
    'mage/translate',
    'mage/loader'
], function ($, registry, confirm, alert, createSafeMessageContent, $t) {
    'use strict';

    /**
     * Check if a value contains a comma.
     *
     * @param {*} val
     * @return {boolean}
     */
    function checkComma(val) {
        return val !== null && val !== undefined && String(val).indexOf(',') !== -1;
    }

    /**
     * Inspect shipment payload for comma errors in numeric fields.
     *
     * @param {Object} payload
     * @return {boolean}
     */
    function validateCommas(payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }

        var hasCommaError = false;

        // 1. Special Services
        if (payload.specialServices) {
            if (checkComma(payload.specialServices.insuranceValue) ||
                checkComma(payload.specialServices.collectOnDeliveryValue) ||
                checkComma(payload.specialServices.codAmount)
            ) {
                hasCommaError = true;
            }
        }

        // 2. Shipment Settings & Packages
        if (payload.shipmentSettings) {
            if (checkComma(payload.shipmentSettings.returnLabelExpDays)) {
                hasCommaError = true;
            }
            if (Array.isArray(payload.shipmentSettings.packages)) {
                $.each(payload.shipmentSettings.packages, function (i, pkg) {
                    if (pkg && (checkComma(pkg.weight) || checkComma(pkg.width) ||
                        checkComma(pkg.length) || checkComma(pkg.height) || checkComma(pkg.quantity))
                    ) {
                        hasCommaError = true;
                    }
                });
            }
        }

        // 3. Packages fieldset
        if (payload.packages_fieldset && Array.isArray(payload.packages_fieldset.packages)) {
            $.each(payload.packages_fieldset.packages, function (i, pkg) {
                if (pkg && (checkComma(pkg.weight) || checkComma(pkg.width) ||
                    checkComma(pkg.length) || checkComma(pkg.height) || checkComma(pkg.quantity))
                ) {
                    hasCommaError = true;
                }
            });
        }

        // 4. Customs Clearance
        if (payload.customs) {
            if (checkComma(payload.customs.costsOfShipment) || checkComma(payload.customs.grossWeight)) {
                hasCommaError = true;
            }
            if (Array.isArray(payload.customs.customsItems)) {
                $.each(payload.customs.customsItems, function (i, item) {
                    if (item && (checkComma(item.weight) || checkComma(item.value) || checkComma(item.quantity))) {
                        hasCommaError = true;
                    }
                });
            }
        }

        return hasCommaError;
    }

    /**
     * Recreate shipment via AJAX with modal error reporting.
     *
     * @param {string} confirmTitle
     * @param {string} confirmMessage
     * @param {string} [saveUrl]
     */
    return function (confirmTitle, confirmMessage, saveUrl) {
        confirm({
            title: confirmTitle || $t('Confirm Shipment Re-creation'),
            content: confirmMessage || $t(
                'Clicking "Save & Resend to DHL" will cancel the existing DHL shipment' +
                ' and generate a new shipment for the updated data.'
            ),
            actions: {
                confirm: function () {
                    var formComponent = registry.get('dhlpoland_shipment_form.dhlpoland_shipment_form');
                    var $form = $('form').filter(function () {
                        var act = $(this).attr('action');
                        return act && act.indexOf('dhlpoland/shipment/save') !== -1;
                    });

                    // 1. Run client-side UI Component validation if component exists
                    if (formComponent && typeof formComponent.validate === 'function') {
                        formComponent.validate();
                        if (formComponent.additionalInvalid || (typeof formComponent.isValid === 'function' && !formComponent.isValid())) {
                            if (typeof formComponent.focusInvalid === 'function') {
                                formComponent.focusInvalid();
                            }
                            return false;
                        }
                    }

                    // 2. Extract payload data for JS pre-validation checks
                    var payload = null;
                    if (formComponent && formComponent.source && typeof formComponent.source.get === 'function') {
                        payload = formComponent.source.get('data');
                    }

                    // 3. Pre-submit comma validation check (analogous to shipment-modal.js)
                    if (payload && validateCommas(payload)) {
                        alert({
                            title: $t('Error'),
                            content: $t('Commas are not allowed in numeric fields. Please use a dot (.) as decimal separator.')
                        });
                        return false;
                    }

                    // 4. Prepare target save URL
                    var url = saveUrl;
                    if (!url && $form.length) {
                        url = $form.attr('action');
                    }
                    if (!url) {
                        url = window.location.href;
                    }

                    // 5. Serialize form data for AJAX submission
                    var postData = {};
                    if ($form.length) {
                        $.each($form.serializeArray(), function (i, field) {
                            postData[field.name] = field.value;
                        });
                    } else if (payload) {
                        postData = payload;
                    }
                    postData.isAjax = true;

                    // 6. Show loader and process AJAX request
                    if (typeof $('body').loader === 'function') {
                        $('body').loader();
                    }
                    $('body').trigger('processStart');

                    $.post(url, postData)
                        .done(function (response) {
                            if (response && response.success) {
                                var msg = response.message || $t('Shipment recreated successfully.');
                                // Keep full-page loader active until reload
                                setTimeout(function () {
                                    window.location.reload();
                                }, 300);
                            } else {
                                $('body').trigger('processStop');
                                alert({
                                    title: $t('Error'),
                                    content: createSafeMessageContent(
                                        (response && response.message)
                                            ? response.message
                                            : $t('Error recreating DHL shipment.')
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
