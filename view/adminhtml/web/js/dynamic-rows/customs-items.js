define([
    'underscore',
    'Magento_Ui/js/dynamic-rows/dynamic-rows'
], function (_, DynamicRows) {
    'use strict';

    var TYPE_INDIVIDUAL = 'I',
        TYPE_SIMPLIFIED = 'U',
        TYPE_SHORT_DECLARATION = 'S';

    var columnRules = {
        tariffCode: [TYPE_INDIVIDUAL, TYPE_SHORT_DECLARATION],
        countryOfOrigin: [TYPE_INDIVIDUAL]
    };

    return DynamicRows.extend({
        defaults: {
            customsType: '',
            dndConfig: {
                enabled: false
            },
            columnRules: columnRules,
            imports: {
                onCustomsTypeChange: '${ $.provider }:data.customs.customsType',
                onCustomsTypeChangeTemplate: '${ $.provider }:data.customs_type',
                onCustomsTypeChangeNs: '${ $.ns }.${ $.ns }.general.customs_type:value'
            }
        },

        /**
         * Initialize element (record).
         * Intercepts record.setVisibilityColumn and subscribes to record elements
         * to enforce customs type column visibility rules.
         *
         * @param {Object} elem
         * @returns {Object} Chainable.
         */
        initElement: function (elem) {
            var self = this;

            this._super(elem);

            if (elem && _.isFunction(elem.setVisibilityColumn)) {
                var originalSetVisibility = elem.setVisibilityColumn.bind(elem);

                elem.setVisibilityColumn = function (index, state) {
                    if (self.isColumnConfigured(index)) {
                        state = self.isColumnAllowed(index);
                    }

                    return originalSetVisibility(index, state);
                };
            }

            if (elem && _.isFunction(elem.elems)) {
                elem.elems.subscribe(function () {
                    self.updateRecordColumnsVisibility(elem);
                });
            }

            this.updateRecordColumnsVisibility(elem);

            return this;
        },

        /**
         * Initialize header elements and apply column visibility rules.
         *
         * @returns {Object} Chainable.
         */
        initHeader: function () {
            this._super();
            this.updateColumnsVisibility();

            return this;
        },

        /**
         * Handler for customs type change.
         *
         * @param {String} value
         */
        onCustomsTypeChange: function (value) {
            if (value !== undefined && value !== null) {
                this.customsType = value;
                this.updateColumnsVisibility();
            }
        },

        onCustomsTypeChangeTemplate: function (value) {
            this.onCustomsTypeChange(value);
        },

        onCustomsTypeChangeNs: function (value) {
            this.onCustomsTypeChange(value);
        },

        /**
         * Check if column has visibility rules configured.
         *
         * @param {String} columnName
         * @returns {Boolean}
         */
        isColumnConfigured: function (columnName) {
            return _.has(this.columnRules, columnName);
        },

        /**
         * Check if column is allowed for the current customs type.
         *
         * @param {String} columnName
         *
         * @returns {Boolean}
         */
        isColumnAllowed: function (columnName) {
            return true;
        },

        /**
         * Update visibility of columns in a specific record row.
         *
         * @param {Object} record
         */
        updateRecordColumnsVisibility: function (record) {
            var self = this,
                recordElems;

            if (!record) {
                return;
            }

            recordElems = _.isFunction(record.elems) ? record.elems() : record.elems;
            if (!recordElems || !recordElems.length) {
                return;
            }

            _.each(this.columnRules, function (allowedTypes, columnName) {
                var isVisible = self.isColumnAllowed(columnName),
                    cell = _.findWhere(recordElems, { index: columnName });

                if (cell && _.isFunction(cell.visible)) {
                    cell.visible(isVisible);
                }
            });
        },

        /**
         * Update visibility of headers and all record cells.
         */
        updateColumnsVisibility: function () {
            var self = this,
                labels = _.isFunction(this.labels) ? this.labels() : this.labels,
                elems = _.isFunction(this.elems) ? this.elems() : this.elems;

            _.each(this.columnRules, function (allowedTypes, columnName) {
                var isVisible = self.isColumnAllowed(columnName),
                    label;

                if (labels && labels.length) {
                    label = _.findWhere(labels, { name: columnName });
                    if (label && _.isFunction(label.visible)) {
                        label.visible(isVisible);
                    }
                }
            });

            if (elems && elems.length) {
                _.each(elems, function (record) {
                    self.updateRecordColumnsVisibility(record);
                });
            }
        }
    });
});
