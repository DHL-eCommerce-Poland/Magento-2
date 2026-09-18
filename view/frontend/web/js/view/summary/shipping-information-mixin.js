define([
    'Magento_Checkout/js/model/quote',
    'Ideo_Dhl/js/model/point-state',
    'Ideo_Dhl/js/model/method-codes',
    'mage/translate'
], function (quote, pointState, methodCodes, $t) {
    'use strict';

    return function (ShippingInformationComponent) {
        return ShippingInformationComponent.extend({
            /**
             * Append selected DHL service point details to shipping method title in checkout summary
             *
             * @return {string} Formatted shipping method title.
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

                var isDhl = carrierCode === methodCodes.CARRIER_CODE
                    || methodCode.indexOf(methodCodes.CARRIER_CODE) === 0
                    || carrierTitle.indexOf('DHL') !== -1;

                if (!isDhl) {
                    return title;
                }

                var pointData = pointState.selectedPoint();

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
