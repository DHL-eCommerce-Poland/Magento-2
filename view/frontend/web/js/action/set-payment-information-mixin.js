define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';

    return function (setPaymentInformationAction) {
        return wrapper.wrap(setPaymentInformationAction, function (originalAction) {
            return originalAction.apply(this, Array.prototype.slice.call(arguments, 1)).done(function () {
                try {
                    window.sessionStorage.removeItem('dhl_selected_service_point');
                } catch (e) {
                    console.warn('DHL Payment Information: Error removing point from storage:', e);
                }
            });
        });
    };
});
