define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'mage/calendar'
], function ($, ko, Component, modal, alert, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            hoursUrl: '',
            bookUrl: ''
        },

        initialize: function () {
            this._super();

            this.selectedShipmentIds = ko.observableArray([]);
            this.pickupDate = ko.observable(new Date().toISOString().slice(0, 10));
            this.availableHours = ko.observableArray([]);
            this.selectedHourSlot = ko.observable('');
            this.additionalInfo = ko.observable('');
            this.isLoading = ko.observable(false);
            this.warningMessage = ko.observable('');

            var self = this;
            var openHandler = function (ids) {
                self.openModal(ids);
            };

            window.IdeoDhlBookCourier = {
                openModal: openHandler
            };

            $('#ideo-dhl-book-courier-modal').data('openBookCourierModal', openHandler);

            return this;
        },

        initCalendar: function () {
            var self = this;
            var $dateInput = $('#ideo_dhl_pickup_date');

            if ($dateInput.length && !$dateInput.hasClass('hasDatepicker')) {
                $dateInput.calendar({
                    dateFormat: 'yy-mm-dd',
                    minDate: new Date(),
                    showsTime: false,
                    changeMonth: true,
                    changeYear: true,
                    onSelect: function (dateText) {
                        self.pickupDate(dateText);
                        self.onDateChange();
                    }
                });
            }
        },

        openModal: function (shipmentIds) {
            var self = this;
            var ids = Array.isArray(shipmentIds) ? shipmentIds : [shipmentIds];
            self.selectedShipmentIds(ids);
            self.pickupDate(new Date().toISOString().slice(0, 10));
            self.warningMessage('');
            self.additionalInfo('');

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
            self.initCalendar();
            self.loadPickupHours();
        },

        onDateChange: function () {
            this.loadPickupHours();
        },

        loadPickupHours: function () {
            var self = this;
            var ids = self.selectedShipmentIds();
            var firstId = ids.length ? ids[0] : null;

            self.isLoading(true);
            self.availableHours([]);
            self.selectedHourSlot('');

            $.ajax({
                url: self.hoursUrl,
                type: 'GET',
                data: {
                    date: self.pickupDate(),
                    shipment_id: firstId
                },
                dataType: 'json',
                success: function (response) {
                    self.isLoading(false);
                    if (response.success && response.data) {
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
                        self.warningMessage($t('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'));
                    }
                },
                error: function () {
                    self.isLoading(false);
                    self.warningMessage($t('zamówienie kuriera na dziś nie jest możliwe zamów kuriera na kolejny dzień'));
                }
            });
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
