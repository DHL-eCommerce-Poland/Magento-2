define([
    'squire',
    'ko'
], function (Squire, ko) {
    'use strict';

    describe('Ideo_Dhl/js/model/point-state', function () {
        var injector,
            quote,
            pointState,
            previousCheckoutConfig,
            method = {
                carrier_code: 'dhlparcel',
                method_code: 'economy_all'
            };

        beforeEach(function (done) {
            window.sessionStorage.removeItem('dhl_selected_service_points');
            previousCheckoutConfig = window.checkoutConfig;
            window.checkoutConfig = {
                shipping: {
                    dhlparcel: {
                        deliveryMode: 'map',
                        pointMethods: ['dhlparcel_economy_all']
                    }
                }
            };
            quote = {
                getQuoteId: jasmine.createSpy('getQuoteId').and.returnValue('quote-1'),
                shippingAddress: ko.observable({countryId: 'PL'}),
                shippingMethod: ko.observable(method)
            };
            injector = new Squire();
            injector.mock({
                'Magento_Checkout/js/model/quote': quote
            });
            injector.require(['Ideo_Dhl/js/model/point-state'], function (loadedPointState) {
                pointState = loadedPointState;
                done();
            });
        });

        afterEach(function () {
            window.sessionStorage.removeItem('dhl_selected_service_points');
            window.checkoutConfig = previousCheckoutConfig;
            injector.clean();
            injector.remove();
        });

        it('keeps the selected point when the shipping country stays the same', function () {
            var point = {sap: 'PL123', country: 'PL'};

            pointState.setSelectedPoint(point, method);
            quote.shippingAddress({countryId: 'PL'});

            expect(pointState.selectedPoint()).toBe(point);
            expect(quote.shippingAddress().extension_attributes.dhl_service_point_id).toBe('PL123');
        });

        it('clears the selected point and address attributes when the shipping country changes', function () {
            var point = {sap: 'DE123', country: 'DE'},
                address = {
                    countryId: 'PL',
                    extension_attributes: {
                        dhl_service_point_id: 'DE123',
                        dhl_service_point_name: 'German pickup point',
                        dhl_service_point_street: 'Main Street 1',
                        dhl_service_point_postcode: '10115',
                        dhl_service_point_city: 'Berlin',
                        dhl_is_packstation: 1,
                        dhl_is_postfiliale: 0,
                        dhl_postnummer: '12345678'
                    }
                };

            quote.shippingAddress({countryId: 'DE'});
            pointState.setSelectedPoint(point, method);
            window.checkoutConfig.shipping.dhlparcel.selectedPoint = {
                sap: 'DE123',
                country: 'DE',
                methodCode: 'dhlparcel_economy_all'
            };
            quote.shippingAddress(address);

            expect(pointState.selectedPoint()).toBeNull();
            expect(address.extension_attributes.dhl_service_point_id).toBeUndefined();
            expect(address.extension_attributes.dhl_service_point_name).toBeUndefined();
            expect(address.extension_attributes.dhl_service_point_street).toBeUndefined();
            expect(address.extension_attributes.dhl_service_point_postcode).toBeUndefined();
            expect(address.extension_attributes.dhl_service_point_city).toBeUndefined();
            expect(address.extension_attributes.dhl_is_packstation).toBeUndefined();
            expect(address.extension_attributes.dhl_is_postfiliale).toBeUndefined();
            expect(address.extension_attributes.dhl_postnummer).toBeUndefined();
            expect(pointState.getPointForMethodKey(method)).toBeNull();
            expect(window.sessionStorage.getItem('dhl_selected_service_points')).toBeNull();
            expect(window.checkoutConfig.shipping.dhlparcel.selectedPoint).toBeNull();
            expect(pointState.validatePointSelection(method)).toBe(false);
        });
    });
});
