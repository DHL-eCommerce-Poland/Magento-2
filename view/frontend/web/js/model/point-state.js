define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Ideo_Dhl/js/model/method-codes',
    'mage/translate'
], function ($, ko, quote, methodCodes, $t) {
    'use strict';

    var STORAGE_KEY = 'dhl_selected_service_points';

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
     * Helper to get full normalized method key string (e.g. 'dhlparcel_economy_all_cod').
     *
     * @param {Object|string|null} method Shipping method object or code.
     *
     * @return {string|null} Normalized method key.
     */
    function getMethodKey(method) {
        if (!method) {
            return null;
        }
        var carrierCode = methodCodes.CARRIER_CODE;
        var mCode = '';
        if (typeof method === 'string') {
            mCode = method;
        } else {
            carrierCode = method.carrier_code || method.carrierCode || methodCodes.CARRIER_CODE;
            mCode = method.method_code || method.methodCode || '';
        }
        if (!mCode) {
            return null;
        }
        if (mCode.indexOf(carrierCode + '_') === 0) {
            return mCode;
        }
        return carrierCode + '_' + mCode;
    }

    /**
     * Persist map of points to sessionStorage.
     *
     * @param {Object} pointsMap Map of methodCode -> point object.
     */
    function saveToStorage(pointsMap) {
        try {
            if (pointsMap && Object.keys(pointsMap).length > 0) {
                var payload = {
                    quoteId: getQuoteId(),
                    points: pointsMap
                };
                window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            } else {
                window.sessionStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {
            console.warn('DHL Point State: Storage save error', e);
        }
    }

    /**
     * Load points map from sessionStorage if valid for current quote.
     *
     * @return {Object} Stored points map or empty object.
     */
    function loadFromStorage() {
        try {
            var saved = window.sessionStorage.getItem(STORAGE_KEY);
            if (saved) {
                var parsed = JSON.parse(saved);
                if (parsed && typeof parsed === 'object') {
                    var currentQuoteId = getQuoteId();
                    if (parsed.quoteId && currentQuoteId && String(parsed.quoteId) !== String(currentQuoteId)) {
                        saveToStorage({});
                        return {};
                    }
                    if (parsed.points && typeof parsed.points === 'object') {
                        return parsed.points;
                    }
                }
            }
        } catch (e) {
            console.warn('DHL Point State: Storage load error', e);
        }
        return {};
    }

    var pointsMap = loadFromStorage();

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
            address.extension_attributes['dhl_is_postfiliale'] = point.isPostfiliale ? 1 : 0;
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
     * Check if a given shipping method requires DHL point selection.
     *
     * @param {Object|null} method Shipping method object.
     *
     * @return {Boolean} True if method requires point selection.
     */
    function isDhlPointMethod(method) {
        if (!method) {
            return false;
        }
        var fullCode = getMethodKey(method);
        if (!fullCode) {
            return false;
        }

        var config = (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel) || {};
        var pointMethods = config.pointMethods || [
            methodCodes.FULL_ECONOMY_ALL,
            methodCodes.FULL_ECONOMY_ALL_COD,
            methodCodes.FULL_ECONOMY_POP,
            methodCodes.FULL_ECONOMY_POP_COD,
            methodCodes.FULL_ECONOMY_BOX,
            methodCodes.FULL_ECONOMY_BOX_COD,
            methodCodes.FULL_CONNECT_PARCELSHOP,
            methodCodes.FULL_CONNECT_PARCELSHOP_COD,
            methodCodes.FULL_CONNECT_PACKSTATION,
            methodCodes.FULL_CONNECT_PACKSTATION_COD
        ];

        if (pointMethods.indexOf(fullCode) !== -1) {
            return true;
        }
        var shortCode = fullCode.indexOf(methodCodes.CARRIER_CODE + '_') === 0 ?
            fullCode.substring(methodCodes.CARRIER_CODE.length + 1) : fullCode;

        return pointMethods.indexOf(shortCode) !== -1;
    }

    /**
     * Lookup point for a given method key in pointsMap.
     *
     * @param {string|null} mKey Method key.
     *
     * @return {Object|null} Point object or null.
     */
    function getPointForMethodKey(mKey) {
        var key = getMethodKey(mKey);
        if (!key) {
            return null;
        }
        if (pointsMap[key]) {
            return pointsMap[key];
        }
        var shortCode = key.indexOf(methodCodes.CARRIER_CODE + '_') === 0 ?
            key.substring(methodCodes.CARRIER_CODE.length + 1) : key;
        if (pointsMap[shortCode]) {
            return pointsMap[shortCode];
        }
        return null;
    }

    /**
     * Update active selected point observable based on specified method or currently active quote shipping method.
     *
     * @param {Object|null} method Shipping method object.
     */
    function updateActivePointForMethod(method) {
        pointErrorMessage('');
        postnummerErrorMessage('');

        if (!method || !isDhlPointMethod(method)) {
            selectedPoint(null);
            updateQuoteExtensionAttributes(null);
            return;
        }

        var mKey = getMethodKey(method);
        var pt = getPointForMethodKey(mKey);

        if (pt && pt.sap) {
            selectedPoint(pt);
            updateQuoteExtensionAttributes(pt);
        } else {
            selectedPoint(null);
            updateQuoteExtensionAttributes(null);
        }
    }

    /**
     * Set selected point for target method or currently selected shipping method.
     *
     * @param {Object|null} point Point data.
     * @param {Object|string|null} [targetMethod] Target method object or method code string.
     */
    function setSelectedPoint(point, targetMethod) {
        var mKey = getMethodKey(targetMethod) || getMethodKey(quote.shippingMethod());
        if (point && mKey) {
            point.quoteId = getQuoteId();
            point.methodCode = mKey;
            pointsMap[mKey] = point;
            saveToStorage(pointsMap);

            var activeKey = getMethodKey(quote.shippingMethod());
            if (activeKey === mKey) {
                selectedPoint(point);
                pointErrorMessage('');
                postnummerErrorMessage('');
                updateQuoteExtensionAttributes(point);
            }
        } else if (!point && mKey) {
            delete pointsMap[mKey];
            var shortCode = mKey.indexOf(methodCodes.CARRIER_CODE + '_') === 0 ?
                mKey.substring(methodCodes.CARRIER_CODE.length + 1) : mKey;
            delete pointsMap[shortCode];

            saveToStorage(pointsMap);

            var activeKey = getMethodKey(quote.shippingMethod());
            if (activeKey === mKey || activeKey === shortCode) {
                selectedPoint(null);
                pointErrorMessage('');
                postnummerErrorMessage('');
                updateQuoteExtensionAttributes(null);
            }
        } else if (!point && !targetMethod) {
            pointsMap = {};
            saveToStorage({});
            selectedPoint(null);
            pointErrorMessage('');
            postnummerErrorMessage('');
            updateQuoteExtensionAttributes(null);
        }
    }

    /**
     * Clear all points or points for specified method.
     *
     * @param {Object|string|null} [targetMethod] Target method object or code.
     */
    function clearPoint(targetMethod) {
        if (targetMethod) {
            setSelectedPoint(null, targetMethod);
        } else {
            pointsMap = {};
            saveToStorage({});
            selectedPoint(null);
            pointErrorMessage('');
            postnummerErrorMessage('');
            updateQuoteExtensionAttributes(null);
        }
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
            setSelectedPoint(point, quote.shippingMethod());
        }
    }

    // Initialize initial point state from checkoutConfig
    if (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel && window.checkoutConfig.shipping.dhlparcel.selectedPoint) {
        var initPoint = window.checkoutConfig.shipping.dhlparcel.selectedPoint;
        if (initPoint && initPoint.sap) {
            var initKey = initPoint.methodCode || getMethodKey(quote.shippingMethod());
            if (initKey) {
                initPoint.methodCode = initKey;
                initPoint.quoteId = getQuoteId();
                pointsMap[initKey] = initPoint;
                saveToStorage(pointsMap);
            }
        }
    }

    // Activate initial point for currently selected shipping method
    updateActivePointForMethod(quote.shippingMethod());

    // Subscribe to shippingAddress updates to keep extension_attributes attached when address updates
    quote.shippingAddress.subscribe(function (newAddress) {
        if (newAddress) {
            updateQuoteExtensionAttributes(selectedPoint());
        }
    });

    // Subscribe to shippingMethod updates to switch active point per shipping method
    quote.shippingMethod.subscribe(function (newMethod) {
        updateActivePointForMethod(newMethod);
    });

    return {
        selectedPoint: selectedPoint,
        pointErrorMessage: pointErrorMessage,
        postnummerErrorMessage: postnummerErrorMessage,
        setSelectedPoint: setSelectedPoint,
        clearPoint: clearPoint,
        setPostnummer: setPostnummer,
        getQuoteId: getQuoteId,
        updateQuoteExtensionAttributes: updateQuoteExtensionAttributes,
        getMethodKey: getMethodKey,
        getPointForMethodKey: getPointForMethodKey,
        updateActivePointForMethod: updateActivePointForMethod,

        /**
         * Resolve destination country code for current quote/point.
         *
         * @return {string} Uppercase 2-letter country code.
         */
        getDestinationCountry: function () {
            var address = quote.shippingAddress();
            if (address && (address.countryId || address.country_id)) {
                return String(address.countryId || address.country_id).toUpperCase();
            }
            if (typeof $ !== 'undefined') {
                var formCountry = $('select[name="country_id"]').val();
                if (formCountry) {
                    return String(formCountry).toUpperCase();
                }
            }
            var point = selectedPoint();
            if (point && point.country) {
                return String(point.country).toUpperCase();
            }
            return 'PL';
        },

        /**
         * Check if a given shipping method requires DHL point selection.
         *
         * @param {Object|null} method Shipping method object.
         *
         * @return {Boolean} True if method requires point selection.
         */
        isDhlPointMethod: isDhlPointMethod,

        /**
         * Check if method is configured for automatic nearest service point delivery.
         *
         * @param {Object|null} method Shipping method object.
         *
         * @return {Boolean} True if nearest mode active.
         */
        isNearestDeliveryMode: function (method) {
            if (!method) {
                return false;
            }
            var methodCode = method.method_code || method.methodCode || '';
            var config = (window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel) || {};
            return (methodCode === methodCodes.METHOD_ECONOMY_ALL || methodCode === methodCodes.FULL_ECONOMY_ALL) && config.deliveryMode === 'nearest';
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
            var country = this.getDestinationCountry();
            if (country !== 'DE') {
                return false;
            }

            var curMethod = quote.shippingMethod();
            var mCode = curMethod ? (curMethod.method_code || curMethod.methodCode || '') : '';
            var fullMCode = curMethod ? ((curMethod.carrier_code || curMethod.carrierCode || '') + '_' + mCode) : '';
            var cleanCode = mCode.indexOf(methodCodes.CARRIER_CODE + '_') === 0 ?
                mCode.substring(methodCodes.CARRIER_CODE.length + 1) : mCode;

            return cleanCode === methodCodes.METHOD_CONNECT_PACKSTATION ||
                cleanCode === methodCodes.METHOD_CONNECT_PACKSTATION_COD ||
                fullMCode === methodCodes.FULL_CONNECT_PACKSTATION ||
                fullMCode === methodCodes.FULL_CONNECT_PACKSTATION_COD;
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
            var country = this.getDestinationCountry();
            return country === 'DE' && !this.isDePackstationSelected();
        },

        /**
         * Validate point selection for shipping method.
         *
         * @param {Object|null} method Shipping method object.
         *
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

            var country = this.getDestinationCountry();
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
