define([
    'Magento_Ui/js/form/element/single-checkbox',
    'uiRegistry'
], function (SingleCheckbox, registry) {
    'use strict';

    return SingleCheckbox.extend({
        defaults: {
            targetField: '',
            orderGrandTotal: '',
            imports: {
                orderGrandTotal: '${ $.provider }:data.orderGrandTotal'
            }
        },

        /**
         * Handle checked state changes for checkbox / toggle.
         * Auto-fills the target field with order grand total when toggled on and target is empty.
         *
         * @param {Boolean} newChecked
         */
        onCheckedChanged: function (newChecked) {
            this._super(newChecked);

            if (!newChecked || !this.targetField) {
                return;
            }

            var grandTotal = this.getOrderGrandTotal();
            if (!grandTotal) {
                return;
            }

            var targetPath = this.targetField.indexOf('.') !== -1
                ? this.targetField
                : (this.parentName ? this.parentName + '.' + this.targetField : this.targetField);

            registry.get(targetPath, function (target) {
                if (!target) {
                    return;
                }

                var currentVal = target.value();
                if (currentVal === null || currentVal === undefined || String(currentVal).trim() === '') {
                    target.value(grandTotal);
                }
            });
        },

        /**
         * Retrieve order grand total from property or data provider.
         *
         * @returns {String}
         */
        getOrderGrandTotal: function () {
            if (this.orderGrandTotal) {
                return String(this.orderGrandTotal);
            }

            if (this.source) {
                var total = this.source.get('data.orderGrandTotal') ||
                    this.source.get('data.specialServices.orderGrandTotal') ||
                    '';

                return total ? String(total) : '';
            }

            return '';
        }
    });
});
