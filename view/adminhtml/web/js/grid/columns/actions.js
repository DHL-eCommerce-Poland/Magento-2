define([
    'Magento_Ui/js/grid/columns/actions',
    'jquery',
    'mage/translate'
], function (Actions, $) {
    'use strict';

    return Actions.extend({
        /**
         * Checks if specified action requires a handler function.
         *
         * @param {String} actionIndex - Actions' identifier.
         * @param {Number} rowIndex - Index of a row.
         *
         * @returns {Boolean}
         */
        isHandlerRequired: function (actionIndex, rowIndex) {
            var action = this.getAction(rowIndex, actionIndex);

            return this._super(actionIndex, rowIndex) || Boolean(action && action.post);
        },

        /**
         * Callback for single row action 'Order Courier' on shipment grid.
         *
         * @param {String} actionIndex
         * @param {Number|String} recordId
         */
        bookCourierRow: function (actionIndex, recordId) {
            var numbers = [];
            if (this.rows && Array.isArray(this.rows)) {
                this.rows.forEach(function (row) {
                    if (row.entity_id == recordId) {
                        numbers.push(row.shipment_id || row.entity_id);
                    }
                });
            }
            this.openBookCourierModal([recordId], numbers);
        },

        /**
         * Callback for mass action 'Order Courier' on shipment grid.
         *
         * @param {Object} action - Action configuration
         * @param {Object} data - Selections data containing selected entity IDs
         */
        bookCourierSelected: function (action, data) {
            var selectedIds = [];
            var numbers = [];

            if (data && data.selected) {
                selectedIds = data.selected;
                if (this.rows && Array.isArray(this.rows)) {
                    this.rows.forEach(function (row) {
                        if (selectedIds.indexOf(row.entity_id) !== -1 || selectedIds.indexOf(String(row.entity_id)) !== -1) {
                            numbers.push(row.shipment_id || row.entity_id);
                        }
                    });
                }
            }

            this.openBookCourierModal(selectedIds, numbers);
        },

        /**
         * Open book courier modal with given shipment entity IDs and shipment numbers.
         *
         * @param {Array} selectedIds
         * @param {Array} shipmentNumbers
         */
        openBookCourierModal: function (selectedIds, shipmentNumbers) {
            if (!selectedIds || selectedIds.length === 0) {
                alert($.mage.__('Please select shipments for courier booking.'));
                return;
            }

            var modal = $('#ideo-dhl-book-courier-modal');
            if (modal.length && typeof modal.data('openBookCourierModal') === 'function') {
                modal.data('openBookCourierModal')(selectedIds, shipmentNumbers);
            } else if (window.IdeoDhlBookCourier && typeof window.IdeoDhlBookCourier.openModal === 'function') {
                window.IdeoDhlBookCourier.openModal(selectedIds, shipmentNumbers);
            } else {
                alert($.mage.__('Courier booking dialog is loading. Please try again.'));
            }
        }
    });
});
