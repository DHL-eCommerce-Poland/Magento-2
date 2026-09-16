define([
    'mage/utils/wrapper',
    'Ideo_Dhl/js/model/point-state'
], function (wrapper, pointState) {
    'use strict';

    return function (setPaymentInformationAction) {
        return wrapper.wrap(setPaymentInformationAction, function (originalAction) {
            return originalAction.apply(this, Array.prototype.slice.call(arguments, 1)).done(function () {
                pointState.clearPoint();
            });
        });
    };
});
