define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messages',
    'mage/translate',
    'jquery'
], function (wrapper, quote, globalMessageList, $t, $) {
    'use strict';

    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, messageContainer) {
            var shippingAddress = quote.shippingAddress();
            var shippingMethod = quote.shippingMethod();

            if (shippingMethod && (shippingMethod.carrier_code === 'dhlparcel' || shippingMethod.carrierCode === 'dhlparcel')) {
                var methodCode = shippingMethod.method_code || shippingMethod.methodCode || '';
                var fullMethodCode = (shippingMethod.carrier_code || shippingMethod.carrierCode || '') + '_' + methodCode;
                var pointMethods = ['dhlparcel_economy', 'dhlparcel_connect', 'dhlparcel_connect_parcel_shop', 'dhlparcel_connect_packstation', 'economy', 'connect', 'connect_parcel_shop', 'connect_packstation'];

                var isPointMethod = pointMethods.indexOf(fullMethodCode) !== -1 || pointMethods.indexOf(methodCode) !== -1;

                if (methodCode === 'economy' && window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel) {
                    if (window.checkoutConfig.shipping.dhlparcel.deliveryMode === 'nearest') {
                        isPointMethod = false;
                    }
                }

                var savedPoint = null;
                try {
                    var savedData = window.sessionStorage.getItem('dhl_selected_service_point');
                    if (savedData) {
                        savedPoint = JSON.parse(savedData);
                    }
                } catch (e) {
                    savedPoint = null;
                }

                if (isPointMethod && (!savedPoint || !savedPoint.sap)) {
                    var msgNoPoint = $t('Please select a DHL pickup point on the map.');
                    var promptElem = $('.dhl-point-prompt, .dhl-point-selector-wrapper');
                    if (promptElem.length) {
                        $('#dhl_point_selection_error').text(msgNoPoint).show();
                        $('html, body').stop().animate({ scrollTop: promptElem.offset().top - 120 }, 300);
                    } else {
                        globalMessageList.addErrorMessage({ message: msgNoPoint });
                        $('html, body').stop().animate({ scrollTop: 0 }, 300);
                    }
                    return $.Deferred().reject();
                }

                if (savedPoint && typeof savedPoint === 'object' && savedPoint.sap) {
                    var pointCountry = (savedPoint.country || (shippingAddress ? (shippingAddress.countryId || shippingAddress.country_id) : 'PL') || 'PL').toUpperCase();
                    var postnummer = (savedPoint.postnummer || '').trim();
                    var nameStr = (savedPoint.name || savedPoint.sap || '').toLowerCase();
                    var typeStr = (savedPoint.type || savedPoint.ptype || '').toLowerCase();

                    var isPackstation = !!(
                        savedPoint.isPackstation ||
                        savedPoint.packstation ||
                        typeStr === 'packstation' ||
                        typeStr === 'lmecobox' ||
                        typeStr === 'box' ||
                        (savedPoint.ptype || '').toLowerCase() === 'packstation' ||
                        nameStr.indexOf('packstation') !== -1 ||
                        nameStr.indexOf('box') !== -1 ||
                        (/^\d+$/.test((savedPoint.name || '').trim()) && pointCountry === 'DE')
                    );

                    if (pointCountry === 'DE' && isPackstation && !postnummer) {
                        var msgReq = $t('German Customer Number (Postnummer) is required for delivery to a German Packstation.');
                        var inputElem = $('#dhl_postnummer_input');
                        if (inputElem.length) {
                            inputElem.css('border-color', '#e02b27').focus();
                            $('#dhl_postnummer_input_error').text(msgReq).show();
                            $('html, body').stop().animate({ scrollTop: inputElem.offset().top - 120 }, 300);
                        } else {
                            globalMessageList.addErrorMessage({ message: msgReq });
                            $('html, body').stop().animate({ scrollTop: 0 }, 300);
                        }
                        return $.Deferred().reject();
                    }

                    if (pointCountry === 'DE' && postnummer && !/^\d{6,10}$/.test(postnummer)) {
                        var msgInv = $t('Invalid German Customer Number (Postnummer). It must contain between 6 and 10 digits.');
                        var inputElem2 = $('#dhl_postnummer_input, #dhl_postnummer_input_opt');
                        if (inputElem2.length) {
                            inputElem2.css('border-color', '#e02b27').focus();
                            $('#dhl_postnummer_input_error, #dhl_postnummer_input_opt_error').text(msgInv).show();
                            $('html, body').stop().animate({ scrollTop: inputElem2.first().offset().top - 120 }, 300);
                        } else {
                            globalMessageList.addErrorMessage({ message: msgInv });
                            $('html, body').stop().animate({ scrollTop: 0 }, 300);
                        }
                        return $.Deferred().reject();
                    }
                }
            }

            return originalAction(paymentData, messageContainer).done(function (response) {
                try {
                    window.sessionStorage.removeItem('dhl_selected_service_point');
                } catch (e) {
                    console.warn('DHL Place Order: Error removing point from storage:', e);
                }
            }).fail(function (response) {
                if (response && response.responseJSON && response.responseJSON.message) {
                    globalMessageList.addErrorMessage({ message: response.responseJSON.message });
                }
            });
        });
    };
});
