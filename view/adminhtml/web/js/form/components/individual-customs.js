define([
    'Magento_Ui/js/form/components/group',
    'underscore'
], function (Group, _) {
    'use strict';

    return Group.extend({
        defaults: {
            listens: {
                'visible': 'onVisibleChange'
            }
        },

        /**
         * Initialize element and apply the current group visibility.
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
         * Propagate visibility change to child elements so validation rules are bypassed when hidden.
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
                    if (!visible) {
                        if (elem && _.isFunction(elem.error)) {
                            elem.error('');
                        }
                        if (elem && _.isFunction(elem.value)) {
                            elem.value('');
                        }
                        if (elem && _.isFunction(elem.checked)) {
                            elem.checked(false);
                        }
                    }
                });
            }

            if (!visible && _.isFunction(this.error)) {
                this.error(false);
            }
        }
    });
});
