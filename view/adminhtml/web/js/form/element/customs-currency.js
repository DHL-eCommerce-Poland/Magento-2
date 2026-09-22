define([
    'underscore',
    'Magento_Ui/js/form/element/select'
], function (_, Select) {
    'use strict';

    var CURRENCY_GBP = 'GBP',
        CURRENCY_EUR = 'EUR',
        CURRENCY_USD = 'USD',
        CURRENCY_PLN = 'PLN',
        CURRENCY_CHF = 'CHF',
        TYPE_INDIVIDUAL = 'I',
        TYPE_SIMPLIFIED = 'U',
        TYPE_SHORT_DECLARATION = 'S';

    var allowedCurrenciesByType = {};

    allowedCurrenciesByType[TYPE_INDIVIDUAL] = [CURRENCY_GBP, CURRENCY_EUR];
    allowedCurrenciesByType[TYPE_SIMPLIFIED] = [CURRENCY_GBP, CURRENCY_EUR, CURRENCY_USD, CURRENCY_PLN, CURRENCY_CHF];
    allowedCurrenciesByType[TYPE_SHORT_DECLARATION] = [CURRENCY_GBP, CURRENCY_EUR, CURRENCY_USD];

    return Select.extend({
        defaults: {
            allowedCurrenciesByType: allowedCurrenciesByType,
            imports: {
                filterByCustomsType: '${ $.parentName }.customs_type:value'
            }
        },

        /**
         * Filter currency options based on selected customs clearance type.
         *
         * @param {String} customsType
         */
        filterByCustomsType: function (customsType) {
            var allowed = this.allowedCurrenciesByType[customsType],
                source = this.initialOptions || this.options,
                filteredOptions,
                currentValue = this.value();

            if (_.isFunction(source)) {
                source = source();
            }

            if (!source || !source.length) {
                return;
            }

            if (!this.initialOptions) {
                this.initialOptions = source;
            }

            if (!allowed) {
                filteredOptions = source;
            } else {
                filteredOptions = [];
                _.each(allowed, function (currCode) {
                    var found = _.find(source, function (option) {
                        return option.value === currCode;
                    });

                    if (found) {
                        filteredOptions.push(found);
                    }
                });
            }

            this.setOptions(filteredOptions);

            if (allowed && currentValue && !_.contains(allowed, currentValue)) {
                var defaultValue = filteredOptions.length > 0 ? filteredOptions[0].value : '';

                this.value(defaultValue);
            }
        }
    });
});
