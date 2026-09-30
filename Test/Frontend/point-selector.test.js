define([
    'squire'
], function (Squire) {
    'use strict';

    describe('Ideo_Dhl/js/view/point-selector message listener', function () {
        var injector,
            component,
            pointState,
            listener,
            frame,
            container;

        beforeEach(function (done) {
            pointState = {
                getMethodKey: jasmine.createSpy('getMethodKey').and.returnValue('dhlparcel_economy_all'),
                setSelectedPoint: jasmine.createSpy('setSelectedPoint')
            };
            injector = new Squire();
            injector.mock({
                'uiComponent': {
                    extend: function (definition) {
                        return definition;
                    }
                },
                'Magento_Checkout/js/model/quote': {
                    shippingAddress: jasmine.createSpy('shippingAddress').and.returnValue({countryId: 'PL'}),
                    shippingMethod: jasmine.createSpy('shippingMethod').and.returnValue({
                        carrier_code: 'dhlparcel',
                        method_code: 'economy_all'
                    })
                },
                'Ideo_Dhl/js/model/point-state': pointState,
                'Magento_Ui/js/modal/modal': jasmine.createSpy('modal')
            });

            container = document.createElement('div');
            container.id = 'dhl-map-modal';
            frame = document.createElement('iframe');
            Object.defineProperty(frame, 'src', {
                configurable: true,
                value: 'https://parcelshop.dhl.pl/mapa'
            });
            container.appendChild(frame);
            document.body.appendChild(container);
            spyOn(window, 'addEventListener').and.callFake(function (type, callback) {
                if (type === 'message') {
                    listener = callback;
                }
            });
            injector.require(['Ideo_Dhl/js/view/point-selector'], function (loadedComponent) {
                component = loadedComponent;
                component.initListeners.call({
                    targetMethodObj: null,
                    getQuoteId: function () {
                        return 'quote-1';
                    },
                    closeMapModal: jasmine.createSpy('closeMapModal')
                });
                done();
            });
        });

        afterEach(function () {
            if (container && container.parentNode) {
                container.parentNode.removeChild(container);
            }
            injector.clean();
            injector.remove();
        });

        it('accepts a point selection from the configured map iframe', function () {
            listener({
                data: {sap: 'PL123', name: 'Pickup point'},
                origin: 'https://parcelshop.dhl.pl',
                source: frame.contentWindow
            });

            expect(pointState.setSelectedPoint).toHaveBeenCalled();
            expect(pointState.setSelectedPoint.calls.mostRecent().args[0].sap).toBe('PL123');
        });

        it('ignores messages from other origins and windows', function () {
            listener({
                data: {sap: 'EVIL1'},
                origin: 'https://example.com',
                source: frame.contentWindow
            });
            listener({
                data: {sap: 'EVIL2'},
                origin: 'https://parcelshop.dhl.pl',
                source: window
            });

            expect(pointState.setSelectedPoint).not.toHaveBeenCalled();
        });
    });
});
