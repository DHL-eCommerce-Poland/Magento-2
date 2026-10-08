define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Ideo_Dhl/js/model/point-state',
    'Magento_Ui/js/model/messageList',
    'jquery'
], function (wrapper, quote, pointState, globalMessageList, $) {
    'use strict';

    return function (setShippingInformationAction) {
        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
            var selectedMethod = quote.shippingMethod();

            if (selectedMethod) {
                if (!pointState.validatePointSelection(selectedMethod)) {
                    var errorMsg = pointState.pointErrorMessage() || pointState.postnummerErrorMessage();
                    if (errorMsg) {
                        globalMessageList.addErrorMessage({ message: errorMsg });
                    }
                    var $input = $('#dhl_postnummer_inline:visible');
                    if (pointState.postnummerErrorMessage() && $input.length) {
                        $input.first().focus();
                        $('html, body').stop().animate({ scrollTop: $input.first().offset().top - 120 }, 300);
                    } else {
                        var $targetWrapper = $('[data-dhl-method]');
                        if ($targetWrapper.length) {
                            $('html, body').stop().animate({ scrollTop: $targetWrapper.first().offset().top - 120 }, 300);
                        } else {
                            $('html, body').stop().animate({ scrollTop: 0 }, 300);
                        }
                    }
                    return $.Deferred().reject();
                }
            }

            pointState.updateQuoteExtensionAttributes(pointState.selectedPoint());

            return originalAction().fail(function (response) {
                if (response && response.responseJSON && response.responseJSON.message) {
                    globalMessageList.addErrorMessage({ message: response.responseJSON.message });
                }
            });
        });
    };
});
