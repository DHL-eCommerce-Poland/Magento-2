define([
    'Magento_Ui/js/grid/columns/actions',
    'jquery',
    'mage/translate'
], function (Actions, $) {
    'use strict';

    return Actions.extend({
        /**
         * Callback for mass action 'Order Courier' on shipment grid.
         *
         * @param {Object} action - Action configuration
         * @param {Object} data - Selections data containing selected entity IDs
         */
        bookCourierSelected: function (action, data) {
            var selectedIds = [];

            if (data && data.selected) {
                selectedIds = data.selected;
            }

            if (!selectedIds || selectedIds.length === 0) {
                alert($.mage.__('Please select shipments for courier booking.'));
                return;
            }

            var modal = $('#ideo-dhl-book-courier-modal');
            if (modal.length && typeof modal.data('openBookCourierModal') === 'function') {
                modal.data('openBookCourierModal')(selectedIds);
            } else if (window.IdeoDhlBookCourier && typeof window.IdeoDhlBookCourier.openModal === 'function') {
                window.IdeoDhlBookCourier.openModal(selectedIds);
            } else {
                alert($.mage.__('Courier booking dialog is loading. Please try again.'));
            }
        }
    });
});
