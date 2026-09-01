define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, ko, Component, modal, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            generateUrl: ''
        },

        initialize: function () {
            this._super();

            var self = this;

            var openHandler = function () {
                self.openModal();
            };

            window.IdeoDhlPnp = {
                openModal: openHandler
            };

            $('#ideo-dhl-pnp-modal').data('openPnpModal', openHandler);

            return this;
        },

        openModal: function () {
            var $modal = $('#ideo-dhl-pnp-modal');
            if (!$modal.data('mageModal')) {
                $modal.modal({
                    title: $t('Courier Report (PnP)'),
                    type: 'slide',
                    buttons: [{
                        text: $t('Generate Report'),
                        class: 'action-primary action-accept',
                        click: function () {
                            $('#ideo-dhl-pnp-form').submit();
                            $modal.modal('closeModal');
                        }
                    }, {
                        text: $t('Cancel'),
                        class: 'action-secondary action-dismiss',
                        click: function () {
                            $modal.modal('closeModal');
                        }
                    }]
                });
            }

            $modal.modal('openModal');
        }
    });
});
