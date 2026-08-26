define([
    'uiComponent',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/checkout-data',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function (Component, $, quote, shippingService, checkoutData, modal, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Ideo_Dhl/point-selector',
            selectedPoint: null,
            isModalOpen: false,
            isPointSelected: false
        },

        /**
         * Initialize observables and shipping method listeners.
         */
        initObservable: function () {
            this._super();
            this.observe(['selectedPoint', 'isModalOpen', 'isPointSelected']);

            var self = this;

            /**
             * Update point selector visibility from the current checkout selection.
             */
            var updatePointSelectionState = function () {
                var method = self.getSelectedShippingMethod();
                self.isPointSelected(self.isDhlPointMethod(method));
            };

            var applyClickedShippingMethod = function (element) {
                var method = self.methodFromCode($(element).val() || '');
                if (!method) {
                    return;
                }

                self._dhlClickedShippingMethod = method;
                updatePointSelectionState();

                clearTimeout(self._dhlClickedShippingMethodTimeout);
                self._dhlClickedShippingMethodTimeout = setTimeout(function () {
                    self._dhlClickedShippingMethod = null;
                    updatePointSelectionState();
                }, 250);
            };

            var updateTimeout;
            var schedulePointSelectionStateUpdate = function () {
                clearTimeout(updateTimeout);
                updateTimeout = setTimeout(updatePointSelectionState, 75);
                self._dhlPointSelectorUpdateTimeout = updateTimeout;
            };

            updatePointSelectionState();

            var clearSelectedPoint = function () {
                self.selectedPoint(null);
                try {
                    window.sessionStorage.removeItem('dhl_selected_service_point');
                } catch (e) {
                    console.warn('DHL Point Selector: Error clearing selected point:', e);
                }

                var address = quote.shippingAddress();
                if (address && address.extension_attributes) {
                    delete address.extension_attributes['dhl_service_point_id'];
                    delete address.extension_attributes['dhl_service_point_name'];
                    delete address.extension_attributes['dhl_service_point_street'];
                    delete address.extension_attributes['dhl_service_point_postcode'];
                    delete address.extension_attributes['dhl_service_point_city'];
                    delete address.extension_attributes['dhl_is_packstation'];
                    delete address.extension_attributes['dhl_is_postfiliale'];
                    delete address.extension_attributes['dhl_postnummer'];
                }
            };

            var lastMethodCode = null;
            quote.shippingMethod.subscribe(function (newMethod) {
                var currentCode = newMethod ? ((newMethod.carrier_code || newMethod.carrierCode || '') + '_' + (newMethod.method_code || newMethod.methodCode || '')) : '';
                if (lastMethodCode !== null && lastMethodCode !== currentCode) {
                    clearSelectedPoint();
                }
                lastMethodCode = currentCode;
                updatePointSelectionState();
            });

            shippingService.getShippingRates().subscribe(function () {
                schedulePointSelectionStateUpdate();
            });

            $(document).on('click.ideoDhlPointSelector', '.table-checkout-shipping-method tr.row', function () {
                var radio = $(this).find('input[type="radio"]').first();
                if (radio.length) {
                    applyClickedShippingMethod(radio[0]);
                }
            });

            return this;
        },

        /**
         * Resolve the currently selected shipping method using Magento's checkout state.
         *
         * The DOM is used only when Magento has rendered a radio selection before its
         * observable state is available.
         *
         * @return {Object|null} Selected shipping method data.
         */
        getSelectedShippingMethod: function () {
            if (this._dhlClickedShippingMethod) {
                return this._dhlClickedShippingMethod;
            }

            var savedRate = checkoutData.getSelectedShippingRate();
            if (savedRate) {
                var rates = shippingService.getShippingRates()();
                var matchingRate = rates.filter(function (rate) {
                    return rate.carrier_code + '_' + rate.method_code === savedRate;
                })[0];

                // The saved rate is the same source used by Magento's radio binding.
                // Prefer it when rates are loaded so an older quote method cannot win.
                if (matchingRate) {
                    return matchingRate;
                }

                // Keep the persisted choice available while rates are still loading.
                if (!rates.length) {
                    return this.methodFromCode(savedRate);
                }
            }

            if (window.checkoutConfig && window.checkoutConfig.selectedShippingMethod) {
                return window.checkoutConfig.selectedShippingMethod;
            }

            var method = quote.shippingMethod();
            if (method && (method.carrier_code || method.carrierCode)
                && (method.method_code || method.methodCode)
            ) {
                return method;
            }

            var checkedRadio = $('.table-checkout-shipping-method input[type="radio"]:checked, input[name="shipping_method"]:checked');
            if (checkedRadio.length) {
                return this.methodFromCode(checkedRadio.val() || '');
            }

            return null;
        },

        /**
         * Check whether a shipping method requires the DHL point selector.
         *
         * @param {Object|null} method Shipping method data.
         *
         * @return {Boolean} Whether the point selector is required.
         */
        isDhlPointMethod: function (method) {
            if (!method) {
                return false;
            }

            var carrierCode = method.carrier_code || method.carrierCode || '';
            var methodCode = method.method_code || method.methodCode || '';
            var fullCode = carrierCode + '_' + methodCode;
            var config = this.getDhlConfig();

            if (config.pointMethods && config.pointMethods.length) {
                return config.pointMethods.indexOf(fullCode) !== -1
                    || config.pointMethods.indexOf(methodCode) !== -1;
            }

            return carrierCode === 'dhlparcel'
                && ['economy', 'connect', 'international', 'connect_plus'].indexOf(methodCode) !== -1;
        },

        /**
         * Convert Magento's carrier_method code into method data.
         *
         * @param {string} code Combined carrier and method code.
         *
         * @return {Object|null} Shipping method data or null for an invalid code.
         */
        methodFromCode: function (code) {
            var separatorPosition = code.indexOf('_');
            if (separatorPosition === -1) {
                return null;
            }

            return {
                carrier_code: code.substring(0, separatorPosition),
                method_code: code.substring(separatorPosition + 1)
            };
        },

        /**
         * Release listeners and the DOM observer when the UI component is destroyed.
         *
         * @return {Object} Destroyed component instance.
         */
        destroy: function () {
            $(document).off('.ideoDhlPointSelector');

            clearTimeout(this._dhlPointSelectorUpdateTimeout);
            clearTimeout(this._dhlClickedShippingMethodTimeout);

            return this._super();
        },

        /**
         * Retrieve current quote ID if available.
         *
         * @return {string|number|null} Current quote entity ID or masked quote ID.
         */
        getQuoteId: function () {
            return quote.getQuoteId() || (window.checkoutConfig && window.checkoutConfig.quoteData && window.checkoutConfig.quoteData.entity_id) || null;
        },

        /**
         * Restore selected point from sessionStorage if present and matching current quote.
         */
        restoreSavedPoint: function () {
            try {
                var savedData = window.sessionStorage.getItem('dhl_selected_service_point');
                if (savedData) {
                    var savedPoint = JSON.parse(savedData);
                    if (savedPoint && typeof savedPoint === 'object' && savedPoint.sap) {
                        var currentQuoteId = this.getQuoteId();
                        if (savedPoint.quoteId && currentQuoteId && String(savedPoint.quoteId) !== String(currentQuoteId)) {
                            window.sessionStorage.removeItem('dhl_selected_service_point');
                            this.selectedPoint(null);
                            return;
                        }
                        this.selectedPoint(savedPoint);
                    }
                }
            } catch (e) {
                console.warn('DHL Point Selector: Error restoring point from storage:', e);
            }
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.initListeners();

            var config = this.getDhlConfig();
            if (config && config.selectedPoint && config.selectedPoint.sap) {
                var currentQuoteId = this.getQuoteId();
                config.selectedPoint.quoteId = currentQuoteId;
                this.selectedPoint(config.selectedPoint);
                try {
                    window.sessionStorage.setItem('dhl_selected_service_point', JSON.stringify(config.selectedPoint));
                } catch (e) {
                    console.warn('DHL Point Selector: Error saving initial point to storage:', e);
                }
            } else {
                this.restoreSavedPoint();
            }

            return this;
        },

        /**
         * Retrieve DHL configuration from checkoutConfig
         */
        getDhlConfig: function () {
            return window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel
                ? window.checkoutConfig.shipping.dhlparcel
                : {
                    mapUrl: 'https://parcelshop.dhl.pl/mapa',
                    pointMapType: 'all',
                    deliveryMode: 'map',
                    pointMethods: [
                        'dhlparcel_economy',
                        'dhlparcel_connect',
                        'dhlparcel_international',
                        'dhlparcel_connect_plus'
                    ]
                };
        },

        /**
         * Return method code of selected shipping method
         */
        currentMethodCode: function () {
            var method = this.getSelectedShippingMethod();
            if (!method) {
                return '';
            }

            var carrierCode = method.carrier_code || method.carrierCode || '';
            var methodCode = method.method_code || method.methodCode || '';
            return carrierCode + '_' + methodCode;
        },

        /**
         * Check if current method is domestic SP with nearest delivery mode configured
         */
        /**
         * Check if current method is domestic SP with nearest delivery mode configured
         */
        isNearestDeliveryMode: function () {
            var methodCode = this.currentMethodCode();
            var config = this.getDhlConfig();
            return (methodCode === 'dhlparcel_economy' || methodCode === 'economy') && config.deliveryMode === 'nearest';
        },

        /**
         * Check if selected point is a German Packstation (Locker) requiring Postnummer input
         */
        isDePackstationSelected: function () {
            var point = this.selectedPoint();
            if (!point) {
                return false;
            }

            var country = (point.country || '').toUpperCase();
            if (!country) {
                var address = quote.shippingAddress();
                if (address && (address.countryId || address.country_id)) {
                    country = (address.countryId || address.country_id).toUpperCase();
                }
            }

            if (country !== 'DE') {
                return false;
            }

            var nameStr = (point.name || point.sap || '').toLowerCase();
            var typeStr = (point.type || point.ptype || '').toLowerCase();

            return !!(
                point.isPackstation ||
                point.packstation ||
                typeStr === 'packstation' ||
                typeStr === 'lmecobox' ||
                typeStr === 'box' ||
                (point.ptype || '').toLowerCase() === 'packstation' ||
                nameStr.indexOf('packstation') !== -1 ||
                nameStr.indexOf('box') !== -1 ||
                /^\d+$/.test((point.name || '').trim())
            );
        },

        /**
         * Check if selected point is a German Postfiliale / ParcelShop (Point) with optional Postnummer input
         */
        isDePointSelected: function () {
            var point = this.selectedPoint();
            if (!point) {
                return false;
            }

            var country = (point.country || '').toUpperCase();
            if (!country) {
                var address = quote.shippingAddress();
                if (address && (address.countryId || address.country_id)) {
                    country = (address.countryId || address.country_id).toUpperCase();
                }
            }

            return country === 'DE' && !this.isDePackstationSelected();
        },

        /**
         * Update postnummer value on selected point and session storage
         */
        updatePostnummer: function (data, event) {
            var val = event && event.target ? event.target.value : '';
            var point = this.selectedPoint();
            if (point) {
                point.postnummer = val;
                this.selectedPoint(point);
                try {
                    window.sessionStorage.setItem('dhl_selected_service_point', JSON.stringify(point));
                } catch (e) {
                    console.warn('DHL Point Selector: Error saving postnummer:', e);
                }
            }

            if (val.trim()) {
                $('#dhl_postnummer_input, #dhl_postnummer_input_opt').css('border-color', '#cccccc');
                $('#dhl_postnummer_input_error, #dhl_postnummer_input_opt_error').hide().text('');
            }
        },

        /**
         * Build iframe URL for DHL map modal with live recipient country resolution
         */
        getMapIframeUrl: function () {
            var config = this.getDhlConfig();
            var baseUrl = config.mapUrl || 'https://parcelshop.dhl.pl/mapa';
            var country = 'PL';

            var address = quote.shippingAddress();
            if (address && (address.countryId || address.country_id)) {
                country = address.countryId || address.country_id;
            } else {
                var formCountry = $('select[name="country_id"]').val();
                if (formCountry) {
                    country = formCountry;
                }
            }

            country = (country || 'PL').toUpperCase();

            var params = [];
            if (country && country !== 'PL') {
                params.push('country=' + encodeURIComponent(country));
            }

            if (config) {
                var currentMethod = this.currentMethodCode();
                var mapType = 'all';
                if (config.pointMapTypes && config.pointMapTypes[currentMethod]) {
                    mapType = config.pointMapTypes[currentMethod];
                } else if (config.pointMapType) {
                    mapType = config.pointMapType;
                }

                if (mapType === 'box') {
                    params.push('ptype=packStation');
                } else if (mapType === 'pop') {
                    params.push('ptype=parcelShop');
                }
            }

            return params.length ? baseUrl + '?' + params.join('&') : baseUrl;
        },

        /**
         * Open DHL map popup modal reliably ensuring widget initialization
         */
        openMapModal: function () {
            var self = this;
            this.isModalOpen(true);

            setTimeout(function () {
                var modalContainer = $('#dhl-map-modal');
                if (!modalContainer.length) {
                    return;
                }

                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    title: $t('Select DHL Pickup Point'),
                    modalClass: 'dhl-map-modal-popup',
                    buttons: [],
                    closed: function () {
                        self.isModalOpen(false);
                    }
                };

                if (!modalContainer.data('mageModal')) {
                    modal(options, modalContainer);
                }
                modalContainer.modal('openModal');
            }, 50);
        },

        /**
         * Close DHL map popup modal
         */
        closeMapModal: function () {
            var modalContainer = $('#dhl-map-modal');
            if (modalContainer.length && modalContainer.data('mageModal')) {
                modalContainer.modal('closeModal');
            }
            this.isModalOpen(false);
        },

        /**
         * Attach postMessage event listener to capture point selection
         */
        initListeners: function () {
            var self = this;
            var listenMessage = function (event) {
                if (!event || !event.data) {
                    return;
                }
                var pointData = event.data;
                if (typeof pointData === 'string') {
                    try {
                        pointData = JSON.parse(pointData);
                    } catch (e) {
                        return;
                    }
                }
                if (pointData && typeof pointData === 'object' && pointData.sap) {
                    var address = quote.shippingAddress();
                    var recipientCountry = pointData.country || (address ? (address.countryId || address.country_id) : 'PL') || 'PL';
                    var nameStr = (pointData.name || pointData.sap || '').toLowerCase();
                    var typeStr = (pointData.type || pointData.ptype || '').toLowerCase();

                    var isPackstation = !!(
                        pointData.isPackstation ||
                        pointData.packstation ||
                        typeStr === 'packstation' ||
                        typeStr === 'lmecobox' ||
                        typeStr === 'box' ||
                        (pointData.ptype || '').toLowerCase() === 'packstation' ||
                        nameStr.indexOf('packstation') !== -1 ||
                        nameStr.indexOf('box') !== -1 ||
                        (/^\d+$/.test((pointData.name || '').trim()) && recipientCountry.toUpperCase() === 'DE')
                    );

                    var isPostfiliale = !isPackstation;

                    var formattedPoint = {
                        quoteId: self.getQuoteId(),
                        sap: pointData.sap || '',
                        name: pointData.name || pointData.sap,
                        street: pointData.street || '',
                        streetNo: pointData.streetNo || pointData.houseNo || '',
                        houseNo: pointData.houseNo || pointData.streetNo || '',
                        zip: pointData.zip || pointData.postalCode || '',
                        city: pointData.city || '',
                        country: recipientCountry.toUpperCase(),
                        isPackstation: isPackstation,
                        isPostfiliale: isPostfiliale,
                        type: typeStr,
                        ptype: pointData.ptype || typeStr,
                        postnummer: pointData.postnummer || ''
                    };

                    self.selectedPoint(formattedPoint);
                    try {
                        window.sessionStorage.setItem('dhl_selected_service_point', JSON.stringify(formattedPoint));
                    } catch (e) {
                        console.warn('DHL Point Selector: Error saving selected point to storage:', e);
                    }

                    self.closeMapModal();
                }
            };

            if (window.addEventListener) {
                window.addEventListener('message', listenMessage, false);
            } else if (window.attachEvent) {
                window.attachEvent('onmessage', listenMessage);
            }
        }
    });
});
