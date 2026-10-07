define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry'
], function (Select, registry) {
    'use strict';

    return Select.extend({
        defaults: {
            imports: {
                updateCountry: '${ $.provider }:data.recipient.country',
                updateServiceType: '${ $.provider }:data.shipmentSettings.serviceType'
            },
            listens: {
                value: 'onValueChange'
            }
        },

        /**
         * Initialize component and set up subscriptions.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            this.country = '';
            this.serviceType = '';

            var self = this;
            if (this.value) {
                this.value.subscribe(function (val) {
                    self.onValueChange(val);
                });
            }

            var providerName = this.provider;
            if (providerName) {
                registry.get(providerName, function (source) {
                    self.source = source;

                    self.updateCountry(source.get('data.recipient.country'));
                    self.updateServiceType(source.get('data.shipmentSettings.serviceType'));

                    if (typeof source.on === 'function') {
                        source.on('data.recipient.country', function (country) {
                            self.updateCountry(country);
                        });
                        source.on('data.shipmentSettings.serviceType', function (serviceType) {
                            self.updateServiceType(serviceType);
                        });
                    }
                });
            }

            return this;
        },

        /**
         * Update recipient country.
         *
         * @param {String} country
         */
        updateCountry: function (country) {
            this.country = country;
            this.refreshVisibility();
        },

        /**
         * Update shipment service type.
         *
         * @param {String} serviceType
         */
        updateServiceType: function (serviceType) {
            this.serviceType = serviceType;
            this.refreshVisibility();
        },

        /**
         * Refresh element visibility based on country (non-PL) and service type (SP or EK).
         */
        refreshVisibility: function () {
            var isNonPl = this.country && String(this.country).toUpperCase() !== 'PL';
            var serviceType = this.serviceType ? String(this.serviceType).trim().toUpperCase() : '';
            var isPointService = serviceType === 'SP' || serviceType === 'EK';
            this.visible(isNonPl && isPointService);
        },

        /**
         * Handle selection change for international point type.
         * When 'courier' is selected, reset point ID, point name, and postnummer.
         *
         * @param {String} val
         */
        onValueChange: function (val) {
            if (val === 'courier') {
                this.clearPointFields();
            }
        },

        /**
         * Reset point ID, point name, and postnummer data and UI component values.
         */
        clearPointFields: function () {
            if (this.source) {
                this.source.set('data.recipient.popId', '');
                this.source.set('data.recipient.popName', '');
                this.source.set('data.recipient.postnummer', '');
            }

            // Target UI components by dataScope and name
            var targets = [
                'dataScope = recipient.popId',
                'dataScope = recipient.popName',
                'dataScope = recipient.postnummer',
                'name = recipient[popId]',
                'name = recipient[popName]',
                'name = recipient[postnummer]'
            ];

            targets.forEach(function (query) {
                registry.get(query, function (comp) {
                    if (comp && typeof comp.value === 'function') {
                        comp.value('');
                    }
                });
            });
        }
    });
});
