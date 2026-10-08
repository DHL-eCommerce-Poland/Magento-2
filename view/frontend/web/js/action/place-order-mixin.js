define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Ideo_Dhl/js/model/point-state',
    'Magento_Ui/js/model/messageList',
    'jquery'
], function (wrapper, quote, pointState, messageList, $) {
    'use strict';

    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
            var shippingMethod = quote.shippingMethod();

            if (shippingMethod) {
                if (!pointState.validatePointSelection(shippingMethod)) {
                    var errorMsg = pointState.pointErrorMessage() || pointState.postnummerErrorMessage();
                    if (errorMsg) {
                        messageList.addErrorMessage({ message: errorMsg });
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

            return originalAction(paymentData, messageContainer).done(function () {
                pointState.clearPoint();
            }).fail(function (response) {
                if (response && response.responseJSON && response.responseJSON.message) {
                    messageList.addErrorMessage({ message: response.responseJSON.message });
                }
            });
        });
    };
});
