define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'mage/translate'
], function ($, quote, shippingService, $t) {
    'use strict';

    return function (Shipping) {
        return Shipping.extend({
            initialize: function () {
                this._super();

                var self = this;

                if (this.rates) {
                    this.rates.subscribe(function () {
                        self.scheduleAppendDhlLogos();
                    });
                }

                shippingService.isLoading.subscribe(function (isLoading) {
                    if (!isLoading) {
                        self.scheduleAppendDhlLogos();
                    }
                });

                $(document).ready(function () {
                    self.scheduleAppendDhlLogos();
                });

                self.scheduleAppendDhlLogos();

                return this;
            },

            scheduleAppendDhlLogos: function () {
                var self = this;
                if (this._dhlLogoTimer) {
                    clearTimeout(this._dhlLogoTimer);
                }
                self.appendDhlLogos();
                this._dhlLogoTimer = setTimeout(function () {
                    self.appendDhlLogos();
                }, 150);
            },

            appendDhlLogos: function () {
                var dhlConfig = window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel;
                var logos = dhlConfig && dhlConfig.logos;

                $('input[value^="dhlparcel_"]').each(function () {
                    var $input = $(this);
                    var fullCode = $input.val();
                    var separatorPos = fullCode.indexOf('_');
                    var carrierCode = separatorPos !== -1 ? fullCode.substring(0, separatorPos) : 'dhlparcel';
                    var methodCode = separatorPos !== -1 ? fullCode.substring(separatorPos + 1) : fullCode;

                    var logoUrl = (logos && logos[fullCode]) || (logos && logos[methodCode]) || (logos && logos.default);
                    if (!logoUrl) {
                        return;
                    }

                    var labelId = '#label_method_' + methodCode + '_' + carrierCode;
                    var $methodCell = $(labelId);

                    if (!$methodCell.length) {
                        var $row = $input.closest('tr');
                        if ($row.length) {
                            $methodCell = $row.find('[id^="label_method_"]').first();
                            if (!$methodCell.length) {
                                $methodCell = $row.find('.col-method').last();
                            }
                        }
                    }

                    if ($methodCell.length && !$methodCell.find('.dhl-method-logo-wrapper').length) {
                        $methodCell.prepend(
                            '<div class="dhl-method-logo-wrapper" data-dhl-logo="' + fullCode + '"><img src="' + logoUrl + '" class="dhl-method-logo" alt="DHL"></div>'
                        );
                    }
                });
            },

            validateShippingInformation: function () {
                var method = quote.shippingMethod();
                if (method) {
                    var carrierCode = method.carrier_code || method.carrierCode || '';
                    var methodCode = method.method_code || method.methodCode || '';
                    var fullCode = carrierCode + '_' + methodCode;

                    var pointMethods = [
                        'dhlparcel_economy',
                        'dhlparcel_connect_parcel_shop',
                        'dhlparcel_connect_packstation'
                    ];

                    var isPointMethod = pointMethods.indexOf(fullCode) !== -1 ||
                        (carrierCode === 'dhlparcel' && ['economy', 'connect_parcel_shop', 'connect_packstation'].indexOf(methodCode) !== -1);

                    if (isPointMethod) {
                        var dhlConfig = window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel;
                        var isNearest = (methodCode === 'economy' || fullCode === 'dhlparcel_economy') &&
                            dhlConfig && dhlConfig.deliveryMode === 'nearest';

                        if (!isNearest) {
                            var pointData = null;
                            try {
                                var stored = window.sessionStorage.getItem('dhl_selected_service_point');
                                if (stored) {
                                    pointData = JSON.parse(stored);
                                }
                            } catch (e) {}

                            if (!pointData || !pointData.sap) {
                                this.errorValidationMessage($t('Please select a DHL pickup point from the map.'));
                                $('.dhl-point-error').text($t('Please select a DHL pickup point from the map.')).show();
                                return false;
                            }

                            if (pointData.country === 'DE' && pointData.isPackstation && (!pointData.postnummer || !pointData.postnummer.trim())) {
                                this.errorValidationMessage($t('Please enter your German Customer Number (Postnummer).'));
                                $('.dhl-postnummer-error').text($t('Please enter your German Customer Number (Postnummer).')).show();
                                return false;
                            }
                        }
                    }
                }

                return this._super();
            }
        });
    };
});
