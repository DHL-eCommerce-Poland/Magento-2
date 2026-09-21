define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Ideo_Dhl/js/model/point-state',
    'Magento_Ui/js/model/messages',
    'jquery'
], function (wrapper, quote, pointState, globalMessageList, $) {
    'use strict';

    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
            var shippingMethod = quote.shippingMethod();

            if (shippingMethod) {
                if (!pointState.validatePointSelection(shippingMethod)) {
                    var errorMsg = pointState.pointErrorMessage() || pointState.postnummerErrorMessage();
                    if (errorMsg) {
                        globalMessageList.addErrorMessage({ message: errorMsg });
                    }
                    var $input = $('#dhl_postnummer_inline:visible, #dhl_postnummer_inline_opt:visible');
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

            return originalAction(paymentData, messageContainer).done(function () {
                pointState.clearPoint();
            }).fail(function (response) {
                if (response && response.responseJSON && response.responseJSON.message) {
                    globalMessageList.addErrorMessage({ message: response.responseJSON.message });
                }
            });
        });
    };
});
