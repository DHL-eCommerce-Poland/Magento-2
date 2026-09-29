define([
    'Magento_Ui/js/form/element/abstract'
], function (Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            imports: {
                updateCountry: '${ $.provider }:data.recipient.country',
                updateServiceType: '${ $.provider }:data.shipmentSettings.serviceType',
                updateMapPointType: '${ $.provider }:data.recipient.mapPointType'
            }
        },

        /**
         * Initialize component and set up country and serviceType properties.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            this.country = '';
            this.serviceType = '';
            return this;
        },

        /**
         * Update recipient country.
         *
         * @param {String} country
         */
        updateCountry: function (country) {
            this.country = country;
            this.refreshState();
        },

        /**
         * Update shipment service type.
         *
         * @param {String} serviceType
         */
        updateServiceType: function (serviceType) {
            this.serviceType = serviceType;
            this.refreshState();
        },

        /**
         * Clear element value when international point type is set to 'courier'.
         *
         * @param {String} mapPointType
         */
        updateMapPointType: function (mapPointType) {
            if (mapPointType === 'courier') {
                this.value('');
            }
        },

        /**
         * Refresh element state (visible only for Germany / DE and SP/EK service).
         */
        refreshState: function () {
            var countryUpper = this.country ? String(this.country).toUpperCase() : '';
            var isGermany = countryUpper === 'DE';
            var st = this.serviceType ? String(this.serviceType).toUpperCase() : '';
            var isPointService = st === 'SP' || st === 'EK';
            var isVisible = isGermany && isPointService;

            this.visible(isVisible);
        }
    });
});
