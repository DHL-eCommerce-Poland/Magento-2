define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Ideo_Dhl/js/model/point-state',
    'Ideo_Dhl/js/model/method-codes',
    'uiRegistry',
    'mage/translate',
    'require'
], function ($, ko, quote, shippingService, pointState, methodCodes, registry, $t, require) {
    'use strict';

    return function (Shipping) {
        return Shipping.extend({
            initialize: function () {
                this._super();

                var self = this;

                if (this.rates) {
                    this.rates.subscribe(function () {
                        self.scheduleDomObserver();
                    });
                }

                shippingService.getShippingRates().subscribe(function () {
                    self.scheduleDomObserver();
                });

                shippingService.isLoading.subscribe(function (isLoading) {
                    if (!isLoading) {
                        self.scheduleDomObserver();
                    }
                });

                self.scheduleDomObserver();

                return this;
            },

            /**
             * Observe checkout DOM changes using MutationObserver to detect shipping method rows asynchronously.
             */
            scheduleDomObserver: function () {
                var self = this;

                if (self._dhlObserver) {
                    self._dhlObserver.disconnect();
                    self._dhlObserver = null;
                }

                if (self.bindDhlMethodAddons()) {
                    return;
                }

                var targetNode = document.querySelector('.table-checkout-shipping-method') || document.body;
                if (!targetNode || typeof MutationObserver === 'undefined') {
                    setTimeout(function () {
                        self.bindDhlMethodAddons();
                    }, 100);
                    return;
                }

                self._dhlObserver = new MutationObserver(function (mutations, observer) {
                    if (self.bindDhlMethodAddons()) {
                        observer.disconnect();
                        self._dhlObserver = null;
                    }
                });

                self._dhlObserver.observe(targetNode, {
                    childList: true,
                    subtree: true
                });
            },

            /**
             * Attach DHL logo (prepended above title) and reactive Knockout point selector controls to .col-method cells.
             *
             * @return {Boolean} True if DHL method inputs were found and bound.
             */
            bindDhlMethodAddons: function () {
                var self = this;
                var $dhlInputs = $('input[value^="' + methodCodes.CARRIER_CODE + '_"]');

                if (!$dhlInputs.length) {
                    return false;
                }

                var defaultLogoUrl = require.toUrl('Ideo_Dhl/images/logo_dhl.png');
                var boxPopLogoUrl = require.toUrl('Ideo_Dhl/images/logo_dhl_box_pop.png');
                var dhlConfig = (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlpoland) || {};
                var configuredLogos = dhlConfig.logos || {};

                $dhlInputs.each(function () {
                    var $input = $(this);
                    var $row = $input.closest('tr');
                    if (!$row.length) {
                        return;
                    }

                    if ($row.attr('data-dhl-bound') === 'true') {
                        return;
                    }

                    var fullCode = $input.val();
                    var separatorPos = fullCode.indexOf('_');
                    var carrierCode = separatorPos !== -1 ? fullCode.substring(0, separatorPos) : methodCodes.CARRIER_CODE;
                    var methodCode = separatorPos !== -1 ? fullCode.substring(separatorPos + 1) : fullCode;
                    var methodObj = { carrier_code: carrierCode, method_code: methodCode };

                    var labelId = '#label_method_' + methodCode + '_' + carrierCode;
                    var $cell = $row.find(labelId);
                    if (!$cell.length) {
                        $cell = $row.find('.col-method').last();
                    }
                    if (!$cell.length) {
                        return;
                    }

                    $row.attr('data-dhl-bound', 'true');

                    var isInternationalMethod = methodCode.indexOf('connect') === 0 || fullCode.indexOf('connect') !== -1;
                    var isPointMethod = pointState.isDhlPointMethod(methodObj);
                    var logoUrl = (configuredLogos && (configuredLogos[fullCode] || configuredLogos[methodCode])) ||
                        (isPointMethod && !isInternationalMethod ? boxPopLogoUrl : defaultLogoUrl);

                    // 1. Prepend logo above method title
                    if (logoUrl && !$cell.find('.dhl-method-logo-wrapper').length) {
                        $cell.prepend(
                            '<div class="dhl-method-logo-wrapper" data-dhl-logo="' + fullCode + '">' +
                                '<img src="' + logoUrl + '" class="dhl-method-logo" alt="DHL">' +
                            '</div>'
                        );
                    }

                    // 2. Append point selector controls wrapper below method title
                    var cellElem = $cell[0];
                    var wrapperSelector = '[data-dhl-method="' + fullCode + '"]';
                    var existingWrapper = cellElem.querySelector(wrapperSelector);

                    if (existingWrapper) {
                        if (ko.dataFor(existingWrapper)) {
                            ko.cleanNode(existingWrapper);
                        }
                        if (existingWrapper.parentNode) {
                            existingWrapper.parentNode.removeChild(existingWrapper);
                        }
                    }

                    var wrapperNode = document.createElement('div');
                    wrapperNode.setAttribute('data-dhl-method', fullCode);
                    wrapperNode.className = 'dhl-method-addon-container';
                    cellElem.appendChild(wrapperNode);

                    if (ko.dataFor(wrapperNode)) {
                        ko.cleanNode(wrapperNode);
                    }

                    var rowViewModel = {
                        name: 'Ideo_Dhl/shipping/method-item-addon',
                        isDhlPointMethod: function () {
                            return isPointMethod;
                        },
                        isMethodSelected: ko.computed(function () {
                            var curMethod = quote.shippingMethod();
                            if (!curMethod) {
                                return false;
                            }
                            var curCarrier = curMethod.carrier_code || curMethod.carrierCode || '';
                            var curCode = curMethod.method_code || curMethod.methodCode || '';
                            var curFullCode = curCarrier + '_' + curCode;

                            return curFullCode === fullCode || curCode === methodCode;
                        }),
                        isNearestDeliveryMode: ko.computed(function () {
                            return pointState.isNearestDeliveryMode(methodObj);
                        }),
                        isPointSelected: ko.computed(function () {
                            var pt = pointState.selectedPoint();
                            var curMethod = quote.shippingMethod();
                            var curCode = curMethod ? ((curMethod.carrier_code || curMethod.carrierCode || '') + '_' + (curMethod.method_code || curMethod.methodCode || '')) : '';
                            return !!(pt && pt.sap && (curCode === fullCode || curCode === methodCode || !curCode));
                        }),
                        selectedPointName: ko.computed(function () {
                            var pt = pointState.selectedPoint();
                            return pt ? (pt.name || pt.sap || '') : '';
                        }),
                        selectedPointStreet: ko.computed(function () {
                            var pt = pointState.selectedPoint();
                            if (!pt) {
                                return '';
                            }
                            var st = pt.street || '';
                            var hn = pt.streetNo || pt.houseNo || '';
                            if (hn && st.indexOf(hn) === -1) {
                                st = (st ? st + ' ' : '') + hn;
                            }
                            return st;
                        }),
                        selectedPointCityZip: ko.computed(function () {
                            var pt = pointState.selectedPoint();
                            if (!pt) {
                                return '';
                            }
                            var zip = pt.zip || pt.postalCode || '';
                            var city = pt.city || '';
                            return (zip + ' ' + city).trim();
                        }),
                        isDePackstationSelected: ko.computed(function () {
                            return pointState.isDePackstationSelected();
                        }),
                        isDePointSelected: ko.computed(function () {
                            return pointState.isDePointSelected();
                        }),
                        postnummerInput: ko.computed({
                            read: function () {
                                var pt = pointState.selectedPoint();
                                return pt ? (pt.postnummer || '') : '';
                            },
                            write: function (value) {
                                pointState.setPostnummer(value);
                            }
                        }),
                        postnummerErrorMessage: ko.computed(function () {
                            var curMethod = quote.shippingMethod();
                            var curCode = curMethod ? ((curMethod.carrier_code || curMethod.carrierCode || '') + '_' + (curMethod.method_code || curMethod.methodCode || '')) : '';
                            if (curCode === fullCode || curCode === methodCode) {
                                return pointState.postnummerErrorMessage();
                            }
                            return '';
                        }),
                        pointErrorMessage: ko.computed(function () {
                            var curMethod = quote.shippingMethod();
                            var curCode = curMethod ? ((curMethod.carrier_code || curMethod.carrierCode || '') + '_' + (curMethod.method_code || curMethod.methodCode || '')) : '';
                            if (curCode === fullCode || curCode === methodCode) {
                                return pointState.pointErrorMessage();
                            }
                            return '';
                        }),
                        stopPropagation: function (data, event) {
                            if (event && event.stopPropagation) {
                                event.stopPropagation();
                            }
                        },
                        openMapModal: function (data, event) {
                            if (event && event.stopPropagation) {
                                event.stopPropagation();
                            }

                            var curMethod = quote.shippingMethod();
                            var curCode = pointState.getMethodKey(curMethod);
                            var targetCode = pointState.getMethodKey(methodObj);
                            if (curCode !== targetCode) {
                                if (typeof self.selectShippingMethod === 'function') {
                                    self.selectShippingMethod(methodObj);
                                }
                                $input.prop('checked', true).trigger('click').trigger('change');
                            }

                            registry.get('checkout.steps.shipping-step.shippingAddress.shippingAdditional.dhl-point-selector', function (pointSelector) {
                                if (pointSelector && typeof pointSelector.openMapModal === 'function') {
                                    pointSelector.openMapModal(methodObj);
                                }
                            });
                        }
                    };

                    wrapperNode.innerHTML = '<div data-bind="template: { name: \'Ideo_Dhl/shipping/method-item-addon\' }"></div>';
                    ko.applyBindings(rowViewModel, wrapperNode);
                });

                return true;
            },

            /**
             * Validate shipping information before stepping forward in checkout.
             *
             * @return {Boolean} True if shipping information is valid.
             */
            validateShippingInformation: function () {
                var method = quote.shippingMethod();
                if (method) {
                    if (!pointState.validatePointSelection(method)) {
                        var msg = pointState.pointErrorMessage() || pointState.postnummerErrorMessage() || $t('Please select a DHL pickup point on the map.');
                        this.errorValidationMessage(msg);
                        var $input = $('#dhl_postnummer_inline:visible, #dhl_postnummer_inline_opt:visible');
                        if (pointState.postnummerErrorMessage() && $input.length) {
                            $input.first().focus();
                            $('html, body').stop().animate({ scrollTop: $input.first().offset().top - 120 }, 300);
                        } else {
                            var $targetWrapper = $('[data-dhl-method]');
                            if ($targetWrapper.length) {
                                $('html, body').stop().animate({ scrollTop: $targetWrapper.first().offset().top - 120 }, 300);
                            }
                        }
                        return false;
                    }
                }
                return this._super();
            }
        });
    };
});
