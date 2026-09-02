define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, ko, Component, modal, $t) {
    'use strict';

    if (!ko.bindingHandlers.dhlDatepicker) {
        ko.bindingHandlers.dhlDatepicker = {
            init: function (element, valueAccessor, allBindings) {
                var value = valueAccessor();
                var options = allBindings.get('datepickerOptions') || {};
                require(['jquery', 'mage/calendar', 'mage/translate'], function ($) {
                    var $el = $(element);
                    if ($el.hasClass('_has-datepicker')) {
                        return;
                    }

                    var config = $.extend({
                        buttonText: $.mage.__('Select Date')
                    }, options);

                    if (options.dateFormat) {
                        config.dateFormat = options.dateFormat;
                    }

                    $el.calendar(config);
                    $el.next('.ui-datepicker-trigger').addClass('v-middle').empty();

                    $el.on('change.dhldatepicker', function () {
                        if (ko.isObservable(value)) {
                            value($el.val());
                        }
                    });

                    ko.utils.domNodeDisposal.addDisposeCallback(element, function () {
                        $el.off('.dhldatepicker');
                        $el.next('.ui-datepicker-trigger').remove();
                        $el.removeClass('_has-datepicker');
                    });
                });
            },
            update: function (element, valueAccessor) {
                var value = ko.unwrap(valueAccessor());
                var $el = $(element);
                if ($el.val() !== (value || '')) {
                    $el.val(value || '');
                }
            }
        };
    }

    return Component.extend({
        defaults: {
            generateUrl: '',
            dateFormat: ''
        },

        initObservable: function () {
            this._super();
            this.date = ko.observable(new Date().toISOString().slice(0, 10));
            return this;
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
            var self = this;

            if ($('#pnp_date').data('datepicker')) {
                $('#pnp_date').datepicker('option', 'minDate', null);
                $('#pnp_date').datepicker('option', 'maxDate', new Date());
                $('#pnp_date').datepicker('setDate', new Date());
                $('#pnp_date').next('.ui-datepicker-trigger').addClass('v-middle').empty();
            }

            var $modal = $('#ideo-dhl-pnp-modal');
            if (!$modal.data('mageModal')) {
                $modal.modal({
                    title: $t('PNP Report'),
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
