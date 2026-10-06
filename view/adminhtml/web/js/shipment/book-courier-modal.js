define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'Ideo_Dhl/js/utils/safe-message-content',
    'mage/loader'
], function ($, ko, Component, modal, alert, $t, createSafeMessageContent) {
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
                    $el.val(value || '').trigger('change');
                }
            }
        };
    }
    ko.bindingHandlers.dhlBookCourierDatepicker = ko.bindingHandlers.dhlDatepicker;

    if (!ko.bindingHandlers.dhlTimepicker) {
        ko.bindingHandlers.dhlTimepicker = {
            init: function (element, valueAccessor) {
                var value = valueAccessor();
                require(['jquery', 'mage/calendar', 'mage/translate'], function ($) {
                    var $el = $(element);
                    if ($el.hasClass('_has-datepicker')) {
                        return;
                    }

                    $el.calendar({
                        showsTime: true,
                        timeOnly: true,
                        timeFormat: 'HH:mm',
                        buttonText: $.mage.__('Select Time')
                    });
                    $el.next('.ui-datepicker-trigger').addClass('v-middle').empty();

                    $el.on('change.dhltimepicker', function () {
                        if (ko.isObservable(value)) {
                            value($el.val());
                        }
                    });

                    ko.utils.domNodeDisposal.addDisposeCallback(element, function () {
                        $el.off('.dhltimepicker');
                        $el.next('.ui-datepicker-trigger').remove();
                        $el.removeClass('_has-datepicker');
                    });
                });
            },
            update: function (element, valueAccessor) {
                var value = ko.unwrap(valueAccessor());
                var $el = $(element);
                if ($el.val() !== (value || '')) {
                    $el.val(value || '').trigger('change');
                }
            }
        };
    }

    return Component.extend({
        defaults: {
            infoUrl: '',
            bookUrl: '',
            dateFormat: ''
        },

        initObservable: function () {
            this._super()
                .observe({
                    selectedShipmentIds: [],
                    selectedShipmentNumbers: [],
                    pickupTimeFrom: '',
                    pickupTimeTo: '',
                    additionalInfo: '',
                    warningMessage: ''
                });

            this.pickupDate = ko.observable($('#dhlpoland_pickup_date').val() || '');
            this.value = this.pickupDate;
            this.isUpdatingDate = false;

            return this;
        },

        initialize: function () {
            this._super();

            var self = this;

            var openHandler = function (ids, numbers) {
                self.openModal(ids, numbers);
            };

            window.IdeoDhlBookCourier = {
                openModal: openHandler
            };

            $('#ideo-dhl-book-courier-modal').data('openBookCourierModal', openHandler);

            return this;
        },

        openModal: function (shipmentIds, shipmentNumbers) {
            var self = this;
            var ids = Array.isArray(shipmentIds) ? shipmentIds : [shipmentIds];
            var numbers = Array.isArray(shipmentNumbers) ? shipmentNumbers : [];

            self.selectedShipmentIds(ids);
            self.selectedShipmentNumbers(numbers);

            self.isUpdatingDate = true;
            self.warningMessage('');
            self.additionalInfo('');
            self.pickupTimeFrom('');
            self.pickupTimeTo('');

            // 1. Try reading directly from form elements on current edit page if available
            var $pageFrom = $('input[name="shipmentSettings[pickupHourFrom]"], input[name="sender[pickupHourFrom]"], input[name*="pickupHourFrom"]');
            var $pageTo = $('input[name="shipmentSettings[pickupHourTo]"], input[name="sender[pickupHourTo]"], input[name*="pickupHourTo"]');

            if ($pageFrom.length && $pageFrom.val()) {
                self.pickupTimeFrom($pageFrom.val().substring(0, 5));
            }
            if ($pageTo.length && $pageTo.val()) {
                self.pickupTimeTo($pageTo.val().substring(0, 5));
            }

            var $dateInput = $('#dhlpoland_pickup_date');
            if ($dateInput.length && ($dateInput.data('datepicker') || $dateInput.hasClass('_has-datepicker'))) {
                $dateInput.datepicker('option', 'minDate', 0);
                $dateInput.datepicker('setDate', new Date());
                $dateInput.next('.ui-datepicker-trigger').addClass('v-middle').empty();
                if ($dateInput.val()) {
                    self.pickupDate($dateInput.val());
                }
            }

            self.isUpdatingDate = false;

            // 2. Fetch saved pickup info from backend for the shipment ID(s)
            if (self.infoUrl && ids && ids.length) {
                $.ajax({
                    url: self.infoUrl,
                    type: 'GET',
                    data: {
                        shipment_id: ids[0],
                        shipment_ids: ids
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response && response.success && response.data) {
                            if (response.data.pickup_time_from !== undefined && response.data.pickup_time_from !== null) {
                                self.pickupTimeFrom(response.data.pickup_time_from.substring(0, 5));
                            }
                            if (response.data.pickup_time_to !== undefined && response.data.pickup_time_to !== null) {
                                self.pickupTimeTo(response.data.pickup_time_to.substring(0, 5));
                            }

                        }
                    }
                });
            }

            var $modal = $('#ideo-dhl-book-courier-modal');
            if (!$modal.data('mageModal')) {
                $modal.modal({
                    title: $t('Order Courier for DHL Shipments'),
                    type: 'slide',
                    buttons: [{
                        text: $t('Order Courier'),
                        class: 'action-primary action-accept',
                        click: function () {
                            self.submitBooking();
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
        },

        onDateChange: function () {
            var $dateInput = $('#dhlpoland_pickup_date');
            if ($dateInput.length) {
                this.pickupDate($dateInput.val());
            }
        },

        submitBooking: function () {
            var self = this;
            var ids = self.selectedShipmentIds();
            var timeFrom = (self.pickupTimeFrom() || '').trim();
            var timeTo = (self.pickupTimeTo() || '').trim();

            if (!ids.length) {
                alert({ content: $t('Please select at least one shipment.') });
                return;
            }

            if (!timeFrom || !timeTo) {
                alert({ content: $t('Both Courier Pickup Time [From] and [To] must be specified.') });
                return;
            }

            $('body').trigger('processStart');

            $.ajax({
                url: self.bookUrl,
                type: 'POST',
                data: {
                    shipment_ids: ids,
                    pickup_date: self.pickupDate() || $('#dhlpoland_pickup_date').val(),
                    pickup_time_from: timeFrom,
                    pickup_time_to: timeTo,
                    additional_info: self.additionalInfo(),
                    form_key: window.FORM_KEY
                },
                dataType: 'json',
                success: function (response) {
                    $('body').trigger('processStop');
                    if (response.success) {
                        $('#ideo-dhl-book-courier-modal').modal('closeModal');
                        window.location.reload();
                    } else {
                        alert({
                            title: $t('Error Booking Courier'),
                            content: createSafeMessageContent(response.message || $t('Failed to book courier.'))
                        });
                    }
                },
                error: function () {
                    $('body').trigger('processStop');
                    alert({
                        title: $t('Error'),
                        content: $t('An unexpected error occurred while contacting the server.')
                    });
                }
            });
        }
    });
});
