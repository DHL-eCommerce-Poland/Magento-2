define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';

    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
            return originalAction(paymentData, messageContainer).done(function (response) {
                try {
                    window.sessionStorage.removeItem('dhl_selected_service_point');
                } catch (e) {
                    console.warn('DHL Place Order: Error removing point from storage:', e);
                }
            });
        });
    };
});
