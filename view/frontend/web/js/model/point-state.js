define([
    'ko',
    'Magento_Checkout/js/model/quote',
    'mage/translate'
], function (ko, quote, $t) {
    'use strict';

    var STORAGE_KEY = 'dhl_selected_service_point';

    var selectedPoint = ko.observable(null);
    var pointErrorMessage = ko.observable('');
    var postnummerErrorMessage = ko.observable('');

    /**
     * Retrieve entity ID or masked ID of current quote.
     *
     * @return {string|number|null} Quote ID.
     */
    function getQuoteId() {
        return quote.getQuoteId() || (window.checkoutConfig && window.checkoutConfig.quoteData && window.checkoutConfig.quoteData.entity_id) || null;
    }

    /**
     * Persist point data to sessionStorage.
     *
     * @param {Object|null} point Selected point data.
     */
    function saveToStorage(point) {
        try {
            if (point) {
                window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(point));
            } else {
                window.sessionStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {
            console.warn('DHL Point State: Storage save error', e);
        }
    }

    /**
     * Load point data from sessionStorage if valid for current quote.
     *
     * @return {Object|null} Stored point or null.
     */
    function loadFromStorage() {
        try {
            var saved = window.sessionStorage.getItem(STORAGE_KEY);
            if (saved) {
                var point = JSON.parse(saved);
                if (point && typeof point === 'object' && point.sap) {
                    var currentQuoteId = getQuoteId();
                    if (point.quoteId && currentQuoteId && String(point.quoteId) !== String(currentQuoteId)) {
                        saveToStorage(null);
                        return null;
                    }
                    return point;
                }
            }
        } catch (e) {
            console.warn('DHL Point State: Storage load error', e);
        }
        return null;
    }

    /**
     * Sync selected point details to shipping address extension attributes.
     *
     * @param {Object|null} point Selected point object.
     */
    function updateQuoteExtensionAttributes(point) {
        var address = quote.shippingAddress();
        if (!address) {
            return;
        }
        if (!address.extension_attributes) {
            address.extension_attributes = {};
        }

        if (point && point.sap) {
            var street = point.street || '';
            var houseNo = point.streetNo || point.houseNo || '';
            if (houseNo && street.indexOf(houseNo) === -1) {
                street = (street ? street + ' ' : '') + houseNo;
            }

            address.extension_attributes['dhl_service_point_id'] = point.sap;
            address.extension_attributes['dhl_service_point_name'] = point.name || point.sap;
            address.extension_attributes['dhl_service_point_street'] = street;
            address.extension_attributes['dhl_service_point_postcode'] = point.zip || point.postalCode || '';
            address.extension_attributes['dhl_service_point_city'] = point.city || '';
            address.extension_attributes['dhl_is_packstation'] = point.isPackstation ? 1 : 0;
            address.extension_attributes['dhl_is_postfiliale'] = point.isPackstation ? 0 : 1;
            address.extension_attributes['dhl_postnummer'] = (point.postnummer || '').trim() || null;
        } else {
            delete address.extension_attributes['dhl_service_point_id'];
            delete address.extension_attributes['dhl_service_point_name'];
            delete address.extension_attributes['dhl_service_point_street'];
            delete address.extension_attributes['dhl_service_point_postcode'];
            delete address.extension_attributes['dhl_service_point_city'];
            delete address.extension_attributes['dhl_is_packstation'];
            delete address.extension_attributes['dhl_is_postfiliale'];
            delete address.extension_attributes['dhl_postnummer'];
        }
    }

    /**
     * Set active selected point and clear validation errors.
     *
     * @param {Object|null} point Point data.
     */
    function setSelectedPoint(point) {
        if (point) {
            point.quoteId = getQuoteId();
        }
        selectedPoint(point);
        pointErrorMessage('');
        postnummerErrorMessage('');
        saveToStorage(point);
        updateQuoteExtensionAttributes(point);
    }

    /**
     * Clear selected point state and storage.
     */
    function clearPoint() {
        selectedPoint(null);
        pointErrorMessage('');
        postnummerErrorMessage('');
        saveToStorage(null);
        updateQuoteExtensionAttributes(null);
    }

    /**
     * Update postnummer for currently selected point.
     *
     * @param {string} postnummer Customer postnummer string.
     */
    function setPostnummer(postnummer) {
        var point = selectedPoint();
        if (point) {
            point.postnummer = postnummer;
            setSelectedPoint(point);
        }
    }

    // Initialize initial point state from checkoutConfig or storage
    var initialPoint = null;
    if (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel && window.checkoutConfig.shipping.dhlparcel.selectedPoint) {
        initialPoint = window.checkoutConfig.shipping.dhlparcel.selectedPoint;
    } else {
        initialPoint = loadFromStorage();
    }
    if (initialPoint && initialPoint.sap) {
        setSelectedPoint(initialPoint);
    }

    // Subscribe to shippingMethod updates to clear point on method switch
    var lastMethodCode = null;
    quote.shippingMethod.subscribe(function (newMethod) {
        var currentCode = newMethod ? ((newMethod.carrier_code || newMethod.carrierCode || '') + '_' + (newMethod.method_code || newMethod.methodCode || '')) : '';
        if (lastMethodCode !== null && lastMethodCode !== currentCode) {
            clearPoint();
        }
        lastMethodCode = currentCode;
    });

    return {
        selectedPoint: selectedPoint,
        pointErrorMessage: pointErrorMessage,
        postnummerErrorMessage: postnummerErrorMessage,
        setSelectedPoint: setSelectedPoint,
        clearPoint: clearPoint,
        setPostnummer: setPostnummer,
        getQuoteId: getQuoteId,

        /**
         * Check if a given shipping method requires DHL point selection.
         *
         * @param {Object|null} method Shipping method object.
         * @return {Boolean} True if method requires point selection.
         */
        isDhlPointMethod: function (method) {
            if (!method) {
                return false;
            }
            var carrierCode = method.carrier_code || method.carrierCode || '';
            var methodCode = method.method_code || method.methodCode || '';
            var fullCode = carrierCode + '_' + methodCode;
            var config = (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel) || {};
            var pointMethods = config.pointMethods || [
                'dhlparcel_economy',
                'dhlparcel_connect_parcel_shop',
                'dhlparcel_connect_packstation'
            ];

            return pointMethods.indexOf(fullCode) !== -1 || pointMethods.indexOf(methodCode) !== -1 ||
                (carrierCode === 'dhlparcel' && ['economy', 'connect_parcel_shop', 'connect_packstation'].indexOf(methodCode) !== -1);
        },

        /**
         * Check if method is configured for automatic nearest service point delivery.
         *
         * @param {Object|null} method Shipping method object.
         * @return {Boolean} True if nearest mode active.
         */
        isNearestDeliveryMode: function (method) {
            if (!method) {
                return false;
            }
            var methodCode = method.method_code || method.methodCode || '';
            var config = (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel) || {};
            return (methodCode === 'economy' || methodCode === 'dhlparcel_economy') && config.deliveryMode === 'nearest';
        },

        /**
         * Check if selected point is a German Packstation (locker).
         *
         * @return {Boolean} True if DE Packstation.
         */
        isDePackstationSelected: function () {
            var point = selectedPoint();
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
                nameStr.indexOf('packstation') !== -1 ||
                nameStr.indexOf('box') !== -1 ||
                /^\d+$/.test((point.name || '').trim())
            );
        },

        /**
         * Check if selected point is a German Postfiliale / ParcelShop.
         *
         * @return {Boolean} True if DE Point.
         */
        isDePointSelected: function () {
            var point = selectedPoint();
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
         * Validate point selection for shipping method.
         *
         * @param {Object|null} method Shipping method object.
         * @return {Boolean} True if selection is valid.
         */
        validatePointSelection: function (method) {
            pointErrorMessage('');
            postnummerErrorMessage('');

            if (!this.isDhlPointMethod(method)) {
                return true;
            }

            if (this.isNearestDeliveryMode(method)) {
                return true;
            }

            var point = selectedPoint();
            if (!point || !point.sap) {
                pointErrorMessage($t('Please select a DHL pickup point on the map.'));
                return false;
            }

            var country = (point.country || '').toUpperCase();
            var postnummer = (point.postnummer || '').trim();

            if (country === 'DE' && this.isDePackstationSelected() && !postnummer) {
                postnummerErrorMessage($t('German Customer Number (Postnummer) is required for delivery to a German Packstation.'));
                return false;
            }

            if (country === 'DE' && postnummer && !/^\d{6,10}$/.test(postnummer)) {
                postnummerErrorMessage($t('Invalid German Customer Number (Postnummer). It must contain between 6 and 10 digits.'));
                return false;
            }

            return true;
        }
    };
});
