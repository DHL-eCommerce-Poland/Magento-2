define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function (wrapper, quote) {
    'use strict';

    return function (setShippingInformationAction) {
        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
            var shippingAddress = quote.shippingAddress();

            if (shippingAddress) {
                if (!shippingAddress.extension_attributes) {
                    shippingAddress.extension_attributes = {};
                }

                var savedPoint = null;
                try {
                    var savedData = window.sessionStorage.getItem('dhl_selected_service_point');
                    if (savedData) {
                        savedPoint = JSON.parse(savedData);
                        var currentQuoteId = quote.getQuoteId() || (window.checkoutConfig && window.checkoutConfig.quoteData && window.checkoutConfig.quoteData.entity_id) || null;
                        if (savedPoint && savedPoint.quoteId && currentQuoteId && String(savedPoint.quoteId) !== String(currentQuoteId)) {
                            window.sessionStorage.removeItem('dhl_selected_service_point');
                            savedPoint = null;
                        }
                    }
                } catch (e) {
                    console.warn('DHL Shipping Information: Error accessing storage:', e);
                    savedPoint = null;
                }

                if (savedPoint && typeof savedPoint === 'object' && savedPoint.sap) {
                    var street = savedPoint.street || '';
                    var houseNo = savedPoint.streetNo || savedPoint.houseNo || '';
                    if (houseNo) {
                        street = (street ? street + ' ' : '') + houseNo;
                    }

                    shippingAddress.extension_attributes['dhl_service_point_id'] = savedPoint.sap;
                    shippingAddress.extension_attributes['dhl_service_point_name'] = savedPoint.name || savedPoint.sap;
                    shippingAddress.extension_attributes['dhl_service_point_street'] = street;
                    shippingAddress.extension_attributes['dhl_service_point_postcode'] = savedPoint.zip || savedPoint.postalCode || '';
                    shippingAddress.extension_attributes['dhl_service_point_city'] = savedPoint.city || '';
                } else {
                    delete shippingAddress.extension_attributes['dhl_service_point_id'];
                    delete shippingAddress.extension_attributes['dhl_service_point_name'];
                    delete shippingAddress.extension_attributes['dhl_service_point_street'];
                    delete shippingAddress.extension_attributes['dhl_service_point_postcode'];
                    delete shippingAddress.extension_attributes['dhl_service_point_city'];
                }
            }

            return originalAction();
        });
    };
});
