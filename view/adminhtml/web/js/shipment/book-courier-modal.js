define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, ko, Component, modal, alert, $t) {
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
                            var dateObj = $el.datepicker('getDate');
                            if (dateObj instanceof Date && !isNaN(dateObj.getTime())) {
                                var yyyy = dateObj.getFullYear();
                                var mm = String(dateObj.getMonth() + 1).padStart(2, '0');
                                var dd = String(dateObj.getDate()).padStart(2, '0');
                                value(yyyy + '-' + mm + '-' + dd);
                            } else {
                                value($el.val());
                            }
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
            hoursUrl: '',
            bookUrl: '',
            dateFormat: ''
        },

        initObservable: function () {
            this._super()
                .observe({
                    selectedShipmentIds: [],
                    selectedShipmentNumbers: [],
                    availableHours: [],
                    selectedHourSlot: '',
                    additionalInfo: '',
                    isLoading: false,
                    warningMessage: ''
                });

            this.pickupDate = ko.observable(new Date().toISOString().slice(0, 10));
            this.value = this.pickupDate;
            this.isUpdatingDate = false;
            this.hoursCache = {};
            this.currentXhr = null;
            this.debounceTimer = null;

            return this;
        },

        initialize: function () {
            this._super();

            var self = this;

            this.pickupDate.subscribe(function (newVal) {
                if (!self.isUpdatingDate && newVal) {
                    self.loadPickupHours();
                }
            });

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
            var numbers = (Array.isArray(shipmentNumbers) && shipmentNumbers.length) ? shipmentNumbers : ids;

            self.selectedShipmentIds(ids);
            self.selectedShipmentNumbers(numbers);

            self.hoursCache = {};
            if (self.currentXhr) {
                self.currentXhr.abort();
                self.currentXhr = null;
            }
            if (self.debounceTimer) {
                clearTimeout(self.debounceTimer);
                self.debounceTimer = null;
            }

            self.isUpdatingDate = true;
            self.warningMessage('');
            self.additionalInfo('');

            var $dateInput = $('#dhlparcel_pickup_date');
            if ($dateInput.length && $dateInput.data('datepicker')) {
                $dateInput.datepicker('option', 'minDate', new Date());
                $dateInput.datepicker('setDate', new Date());
                $dateInput.next('.ui-datepicker-trigger').addClass('v-middle').empty();
                if ($dateInput.val()) {
                    self.pickupDate($dateInput.val());
                }
            }

            self.isUpdatingDate = false;

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
            self.loadPickupHours(true);
        },

        onDateChange: function () {
            this.loadPickupHours();
        },

        loadPickupHours: function (immediate) {
            var self = this;
            var ids = self.selectedShipmentIds();
            var firstId = (ids && ids.length) ? ids[0] : null;
            var date = self.pickupDate();

            if (!ids || !ids.length) {
                return;
            }

            if (self.debounceTimer) {
                clearTimeout(self.debounceTimer);
                self.debounceTimer = null;
            }

            if (self.currentXhr) {
                self.currentXhr.abort();
                self.currentXhr = null;
            }

            var cacheKey = date + '_' + ids.join('_');
            if (self.hoursCache[cacheKey]) {
                self.applyPickupHoursResponse(self.hoursCache[cacheKey]);
                return;
            }

            self.isLoading(true);
            self.availableHours([]);
            self.selectedHourSlot('');
            self.warningMessage('');

            var doRequest = function () {
                self.currentXhr = $.ajax({
                    url: self.hoursUrl,
                    type: 'GET',
                    data: {
                        date: date,
                        shipment_id: firstId,
                        shipment_ids: ids
                    },
                    dataType: 'json',
                    success: function (response) {
                        self.currentXhr = null;
                        self.isLoading(false);
                        if (response && response.success) {
                            self.hoursCache[cacheKey] = response;
                        }
                        self.applyPickupHoursResponse(response);
                    },
                    error: function (xhr, status) {
                        self.currentXhr = null;
                        if (status === 'abort') {
                            return;
                        }
                        self.isLoading(false);
                        self.warningMessage($t('Courier pickup is not available for the selected date. Please select another date in the calendar.'));
                    }
                });
            };

            if (immediate) {
                doRequest();
            } else {
                self.debounceTimer = setTimeout(doRequest, 300);
            }
        },

        applyPickupHoursResponse: function (response) {
            var self = this;
            self.isLoading(false);
            self.availableHours([]);
            self.selectedHourSlot('');

            if (response && response.success && response.data) {
                var data = response.data;
                if (data.message) {
                    self.warningMessage(data.message);
                } else {
                    self.warningMessage('');
                }

                if (data.available_hours && data.available_hours.length > 0) {
                    var formattedSlots = data.available_hours.map(function (slot) {
                        return {
                            label: slot.label,
                            value: slot.from + '|' + slot.to
                        };
                    });
                    self.availableHours(formattedSlots);
                    if (formattedSlots.length > 0) {
                        self.selectedHourSlot(formattedSlots[0].value);
                    }
                }
            } else {
                var msg = (response && response.message) ? response.message : $t('Courier pickup is not available for the selected date. Please select another date in the calendar.');
                self.warningMessage(msg);
            }
        },

        submitBooking: function () {
            var self = this;
            var hourSlot = self.selectedHourSlot();
            var ids = self.selectedShipmentIds();

            if (!ids.length) {
                alert({ content: $t('Please select at least one shipment.') });
                return;
            }

            if (!hourSlot || hourSlot.indexOf('|') === -1) {
                alert({ content: $t('Please select an available pickup time window.') });
                return;
            }

            var times = hourSlot.split('|');
            var timeFrom = times[0];
            var timeTo = times[1];

            $('body').trigger('processStart');

            $.ajax({
                url: self.bookUrl,
                type: 'POST',
                data: {
                    shipment_ids: ids,
                    pickup_date: self.pickupDate(),
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
                        alert({
                            title: $t('Courier Booked'),
                            content: response.message,
                            actions: {
                                always: function () {
                                    window.location.reload();
                                }
                            }
                        });
                    } else {
                        alert({
                            title: $t('Error Booking Courier'),
                            content: response.message || $t('Failed to book courier.')
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
