define([
    'uiComponent',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Ideo_Dhl/js/model/point-state',
    'Ideo_Dhl/js/model/method-codes',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function (Component, $, quote, pointState, methodCodes, modal, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Ideo_Dhl/point-selector',
            isModalOpen: false
        },

        targetMethodObj: null,

        /**
         * Initialize component observables and message listeners.
         */
        initObservable: function () {
            this._super();
            this.observe(['isModalOpen']);

            this.initListeners();

            return this;
        },

        /**
         * Initialize component instance.
         */
        initialize: function () {
            this._super();
            return this;
        },

        /**
         * Retrieve current quote ID.
         *
         * @return {string|number|null} Current quote entity ID or masked quote ID.
         */
        getQuoteId: function () {
            return pointState.getQuoteId();
        },

        /**
         * Retrieve DHL configuration from checkoutConfig
         *
         * @return {Object} DHL configuration object.
         */
        getDhlConfig: function () {
            return (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel)
                ? window.checkoutConfig.shipping.dhlparcel
                : {
                    mapUrl: methodCodes.DEFAULT_MAP_URL,
                    pointMapType: 'all',
                    deliveryMode: 'map',
                    pointMethods: [
                        methodCodes.FULL_ECONOMY_ALL,
                        methodCodes.FULL_ECONOMY_POP,
                        methodCodes.FULL_ECONOMY_BOX,
                        methodCodes.FULL_CONNECT_PARCELSHOP,
                        methodCodes.FULL_CONNECT_PACKSTATION
                    ]
                };
        },

        /**
         * Return method code of target or currently selected shipping method.
         *
         * @return {string} Carrier and method code string.
         */
        currentMethodCode: function () {
            var method = this.targetMethodObj || quote.shippingMethod();
            if (!method) {
                return '';
            }
            return pointState.getMethodKey(method) || '';
        },

        /**
         * Build iframe URL for DHL map modal with recipient country resolution.
         *
         * @return {string} Map iframe URL.
         */
        getMapIframeUrl: function () {
            var config = this.getDhlConfig();
            var baseUrl = config.mapUrl || methodCodes.DEFAULT_MAP_URL;
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
         * Open DHL map popup modal for specified target method.
         *
         * @param {Object|null} [methodObj] Target shipping method object.
         */
        openMapModal: function (methodObj) {
            var self = this;
            if (methodObj) {
                this.targetMethodObj = methodObj;
            } else {
                this.targetMethodObj = quote.shippingMethod();
            }
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
                        self.targetMethodObj = null;
                    }
                };

                if (!modalContainer.data('mageModal')) {
                    modal(options, modalContainer);
                }
                modalContainer.modal('openModal');
            }, 50);
        },

        /**
         * Close DHL map popup modal.
         */
        closeMapModal: function () {
            var modalContainer = $('#dhl-map-modal');
            if (modalContainer.length && modalContainer.data('mageModal')) {
                modalContainer.modal('closeModal');
            }
            this.isModalOpen(false);
            this.targetMethodObj = null;
        },

        /**
         * Attach postMessage event listener to capture map point selection.
         */
        initListeners: function () {
            var self = this;

            this._messageListener = function (event) {
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
                    var formCountry = $('select[name="country_id"]').val();
                    var recipientCountry = pointData.country ||
                        (address ? (address.countryId || address.country_id) : '') ||
                        formCountry ||
                        'PL';
                    var countryUpper = recipientCountry.toUpperCase();
                    var typeStr = (pointData.type || pointData.ptype || '').toLowerCase();

                    var curMethod = self.targetMethodObj || quote.shippingMethod();
                    var mCode = curMethod ? (curMethod.method_code || curMethod.methodCode || '') : '';
                    var fullMCode = pointState.getMethodKey(curMethod) || mCode;
                    var isPackstationMethod = mCode === methodCodes.METHOD_CONNECT_PACKSTATION ||
                        mCode === methodCodes.METHOD_CONNECT_PACKSTATION_COD ||
                        fullMCode === methodCodes.FULL_CONNECT_PACKSTATION ||
                        fullMCode === methodCodes.FULL_CONNECT_PACKSTATION_COD;

                    var isPackstation = isPackstationMethod || !!(
                        pointData.isPackstation ||
                        pointData.packstation ||
                        typeStr === 'packstation' ||
                        (pointData.ptype || '').toLowerCase() === 'packstation'
                    );

                    var isPostfiliale = recipientCountry.toUpperCase() === 'DE' && !isPackstation;

                    var curMethodKey = pointState.getMethodKey(curMethod) || fullMCode;

                    var formattedPoint = {
                        quoteId: self.getQuoteId(),
                        methodCode: curMethodKey,
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

                    pointState.setSelectedPoint(formattedPoint, curMethodKey);
                    self.closeMapModal();
                }
            };

            if (window.addEventListener) {
                window.addEventListener('message', this._messageListener, false);
            } else if (window.attachEvent) {
                window.attachEvent('onmessage', this._messageListener);
            }
        },

        /**
         * Clean up listeners when component is destroyed.
         *
         * @return {Object} Component instance.
         */
        destroy: function () {
            if (this._messageListener) {
                if (window.removeEventListener) {
                    window.removeEventListener('message', this._messageListener, false);
                } else if (window.detachEvent) {
                    window.detachEvent('onmessage', this._messageListener);
                }
            }

            return this._super();
        }
    });
});
