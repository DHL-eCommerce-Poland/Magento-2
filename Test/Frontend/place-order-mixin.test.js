define([
    'squire',
    'jquery'
], function (Squire, $) {
    'use strict';

    describe('Ideo_Dhl/js/action/place-order-mixin', function () {
        var injector,
            mixin,
            quote,
            pointState,
            messageList,
            originalAction;

        beforeEach(function (done) {
            quote = {
                shippingMethod: jasmine.createSpy('shippingMethod').and.returnValue({
                    carrier_code: 'dhlparcel',
                    method_code: 'economy_all'
                })
            };
            pointState = {
                validatePointSelection: jasmine.createSpy('validatePointSelection'),
                pointErrorMessage: jasmine.createSpy('pointErrorMessage').and.returnValue('Select a DHL point.'),
                postnummerErrorMessage: jasmine.createSpy('postnummerErrorMessage').and.returnValue(''),
                clearPoint: jasmine.createSpy('clearPoint')
            };
            messageList = {
                addErrorMessage: jasmine.createSpy('addErrorMessage')
            };
            originalAction = jasmine.createSpy('originalAction').and.returnValue($.Deferred().resolve().promise());

            injector = new Squire();
            injector.mock({
                'mage/utils/wrapper': {
                    wrap: function (action, wrapper) {
                        return function () {
                            return wrapper.apply(this, [action].concat(Array.prototype.slice.call(arguments)));
                        };
                    }
                },
                'Magento_Checkout/js/model/quote': quote,
                'Ideo_Dhl/js/model/point-state': pointState,
                'Magento_Ui/js/model/messageList': messageList
            });
            injector.require(['Ideo_Dhl/js/action/place-order-mixin'], function (loadedMixin) {
                mixin = loadedMixin;
                done();
            });
        });

        afterEach(function () {
            injector.clean();
            injector.remove();
        });

        it('shows the validation error and rejects without placing the order', function () {
            var paymentData = {},
                messageContainer = {},
                result;

            pointState.validatePointSelection.and.returnValue(false);
            result = mixin(originalAction)(paymentData, messageContainer);

            expect(messageList.addErrorMessage).toHaveBeenCalledWith({message: 'Select a DHL point.'});
            expect(originalAction).not.toHaveBeenCalled();
            expect(result.state()).toBe('rejected');
        });

        it('places the order and clears the selected point after a valid selection', function () {
            var paymentData = {method: 'checkmo'},
                messageContainer = {};

            pointState.validatePointSelection.and.returnValue(true);
            mixin(originalAction)(paymentData, messageContainer);

            expect(originalAction).toHaveBeenCalledWith(paymentData, messageContainer);
            expect(pointState.clearPoint).toHaveBeenCalled();
        });

        it('reports a place-order failure to the global message list', function () {
            var paymentData = {},
                messageContainer = {},
                failure = {responseJSON: {message: 'Payment failed.'}},
                deferred = $.Deferred();

            pointState.validatePointSelection.and.returnValue(true);
            originalAction.and.returnValue(deferred.promise());
            mixin(originalAction)(paymentData, messageContainer);
            deferred.reject(failure);

            expect(messageList.addErrorMessage).toHaveBeenCalledWith({message: 'Payment failed.'});
        });
    });
});
