define([
    'Magento_Ui/js/form/components/fieldset',
    'underscore'
], function (Fieldset, _) {
    'use strict';

    return Fieldset.extend({
        defaults: {
            listens: {
                'visible': 'onVisibleChange',
                'opened': 'onVisibilityChange'
            }
        },

        /**
         * Initialize a field and apply the fieldset's current visibility.
         *
         * @param {Object} elem
         *
         * @returns {Object}
         */
        initElement: function (elem) {
            this._super(elem);

            var isVisible = !!this.visible();

            if (elem && _.isFunction(elem.visible)) {
                elem.visible(isVisible);
            }
            if (elem && _.isFunction(elem.disabled)) {
                elem.disabled(!isVisible);
            }

            return this;
        },

        /**
         * Propagate visibility changes so hidden fields are excluded from validation.
         *
         * @param {Boolean} isVisible
         */
        onVisibleChange: function (isVisible) {
            var visible = !!isVisible,
                elements = _.isFunction(this.elems) ? this.elems() : this.elems;

            if (_.isArray(elements)) {
                elements.forEach(function (elem) {
                    if (elem && _.isFunction(elem.visible)) {
                        elem.visible(visible);
                    }
                    if (elem && _.isFunction(elem.disabled)) {
                        elem.disabled(!visible);
                    }
                    if (!visible && elem && _.isFunction(elem.error)) {
                        elem.error('');
                    }
                });
            }

            if (!visible && _.isFunction(this.error)) {
                this.error(false);
            }
        }
    });
});
