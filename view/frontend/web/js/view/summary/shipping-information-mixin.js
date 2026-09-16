define([
    'Magento_Checkout/js/model/quote',
    'uiRegistry',
    'mage/translate'
], function (quote, registry, $t) {
    'use strict';

    return function (ShippingInformationComponent) {
        return ShippingInformationComponent.extend({
            /**
             * Append selected DHL service point details to shipping method title in checkout summary
             */
            getShippingMethodTitle: function () {
                var title = this._super();
                var method = quote.shippingMethod();
                if (!method) {
                    return title;
                }

                var carrierCode = method.carrier_code || method.carrierCode || '';
                var methodCode = method.method_code || method.methodCode || '';
                var carrierTitle = method.carrier_title || method.carrierTitle || '';

                var isDhl = carrierCode === 'dhlparcel'
                    || methodCode.indexOf('dhlparcel') === 0
                    || carrierTitle.indexOf('DHL') !== -1;

                if (!isDhl) {
                    return title;
                }

                var pointData = null;

                // 1. Try sessionStorage first
                try {
                    var savedData = window.sessionStorage.getItem('dhl_selected_service_point');
                    if (savedData) {
                        pointData = JSON.parse(savedData);
                    }
                } catch (e) {
                    console.warn('DHL Shipping Information Summary: Error reading storage:', e);
                }

                // 2. Try active Knockout observable from point-selector component
                if (!pointData) {
                    var pointSelector = registry.get('checkout.steps.shipping-step.shippingAddress.shippingAdditional.dhl-point-selector')
                        || registry.get('checkout.steps.shipping-step.shippingAddress.dhl-point-selector')
                        || registry.get('dhl-point-selector');

                    if (pointSelector && typeof pointSelector.selectedPoint === 'function') {
                        pointData = pointSelector.selectedPoint();
                    }
                }

                // 3. Try quote shipping address extension attributes
                if (!pointData) {
                    var address = quote.shippingAddress();
                    if (address) {
                        var ext = address.extension_attributes || address.extensionAttributes;
                        if (typeof ext === 'function') {
                            ext = ext();
                        }
                        if (ext && (ext.dhl_service_point_id || ext.dhlServicePointId)) {
                            pointData = {
                                sap: ext.dhl_service_point_id || ext.dhlServicePointId,
                                name: ext.dhl_service_point_name || ext.dhlServicePointName || ext.dhl_service_point_id,
                                street: ext.dhl_service_point_street || ext.dhlServicePointStreet || '',
                                zip: ext.dhl_service_point_postcode || ext.dhlServicePointPostcode || '',
                                city: ext.dhl_service_point_city || ext.dhlServicePointCity || ''
                            };
                        }
                    }
                }

                // 4. Try checkoutConfig initial selectedPoint
                if (!pointData && window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel) {
                    var cfg = window.checkoutConfig.shipping.dhlparcel;
                    if (cfg.selectedPoint && cfg.selectedPoint.sap) {
                        pointData = cfg.selectedPoint;
                    }
                }

                if (pointData && (pointData.sap || pointData.id)) {
                    var sapId = pointData.sap || pointData.id || '';
                    var name = pointData.name || sapId;
                    var street = pointData.street || '';
                    var houseNo = pointData.streetNo || pointData.houseNo || '';
                    if (houseNo && street.indexOf(houseNo) === -1) {
                        street = (street ? street + ' ' : '') + houseNo;
                    }
                    var city = pointData.city || '';
                    var zip = pointData.zip || pointData.postalCode || pointData.postcode || '';

                    var addressParts = [];
                    if (street) {
                        addressParts.push(street);
                    }
                    if (zip || city) {
                        addressParts.push((zip + ' ' + city).trim());
                    }
                    var formattedAddress = addressParts.join(', ');

                    var label = $t('Pickup Point') + ': ' + name;
                    if (formattedAddress) {
                        label += ', ' + formattedAddress;
                    }

                    title += ' (' + label + ')';
                }

                return title;
            }
        });
    };
});
