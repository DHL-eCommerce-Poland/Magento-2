define([
    'underscore',
    'Magento_Ui/js/form/element/select'
], function (_, Select) {
    'use strict';

    var CATEGORY_OTHER = '9',
        CATEGORY_SALES_OF_GOODS = '11',
        CATEGORY_RETURN_OF_GOODS = '21',
        CATEGORY_GIFT = '31',
        CATEGORY_COMMERCIAL_SAMPLES = '32',
        CATEGORY_DOCUMENTS = '91',
        TYPE_INDIVIDUAL = 'I',
        TYPE_SIMPLIFIED = 'U',
        TYPE_SHORT_DECLARATION = 'S';

    var allowedCategoriesByType = {};

    allowedCategoriesByType[TYPE_INDIVIDUAL] = [
        CATEGORY_OTHER,
        CATEGORY_SALES_OF_GOODS,
        CATEGORY_RETURN_OF_GOODS,
        CATEGORY_COMMERCIAL_SAMPLES
    ];
    allowedCategoriesByType[TYPE_SIMPLIFIED] = [
        CATEGORY_OTHER,
        CATEGORY_SALES_OF_GOODS,
        CATEGORY_RETURN_OF_GOODS,
        CATEGORY_GIFT,
        CATEGORY_COMMERCIAL_SAMPLES,
        CATEGORY_DOCUMENTS
    ];
    allowedCategoriesByType[TYPE_SHORT_DECLARATION] = [
        CATEGORY_OTHER,
        CATEGORY_SALES_OF_GOODS,
        CATEGORY_RETURN_OF_GOODS,
        CATEGORY_GIFT,
        CATEGORY_COMMERCIAL_SAMPLES,
        CATEGORY_DOCUMENTS
    ];

    return Select.extend({
        defaults: {
            allowedCategoriesByType: allowedCategoriesByType,
            imports: {
                filterByCustomsType: '${ $.parentName }.customs_type:value'
            }
        },

        /**
         * Filter category options based on selected customs clearance type.
         *
         * @param {String} customsType
         */
        filterByCustomsType: function (customsType) {
            var allowed = this.allowedCategoriesByType[customsType],
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
                _.each(allowed, function (catCode) {
                    var found = _.find(source, function (option) {
                        return String(option.value) === String(catCode);
                    });

                    if (found) {
                        filteredOptions.push(found);
                    }
                });
            }

            this.setOptions(filteredOptions);

            if (allowed && currentValue && !_.contains(allowed, String(currentValue))) {
                var defaultValue = filteredOptions.length > 0 ? filteredOptions[0].value : '11';

                this.value(defaultValue);
            }
        }
    });
});
