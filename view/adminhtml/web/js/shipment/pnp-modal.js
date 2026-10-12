define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, ko, Component, modal, $t) {
    'use strict';

    function setDateOnElement($el, rawVal, dateFormat, onFormatted) {
        if (!rawVal) {
            $el.val('');
            if (typeof onFormatted === 'function') {
                onFormatted('');
            }
            return;
        }

        require(['moment', 'mage/utils/misc', 'mage/calendar'], function (moment, utils) {
            var dateObj = null;
            if (rawVal instanceof Date) {
                dateObj = rawVal;
            } else {
                var strVal = String(rawVal).trim();
                if (!strVal) {
                    $el.val('');
                    if (typeof onFormatted === 'function') {
                        onFormatted('');
                    }
                    return;
                }

                if (dateFormat && utils && utils.convertToMomentFormat) {
                    var mFormat = utils.convertToMomentFormat(dateFormat);
                    var mDate = moment(strVal, mFormat, true);
                    if (mDate.isValid()) {
                        dateObj = mDate.toDate();
                    }
                }

                if (!dateObj) {
                    var mIso = moment(strVal, ['YYYY-MM-DD', 'YYYY/MM/DD', moment.ISO_8601], true);
                    if (mIso.isValid()) {
                        dateObj = mIso.toDate();
                    }
                }

                if (!dateObj && $el.data('datepicker')) {
                    try {
                        var activeFormat = $el.datepicker('option', 'dateFormat');
                        dateObj = $.datepicker.parseDate(activeFormat, strVal);
                    } catch (e) {}
                }

                if (!dateObj) {
                    var mLoose = moment(strVal);
                    if (mLoose.isValid()) {
                        dateObj = mLoose.toDate();
                    }
                }
            }

            if (dateObj && $el.data('datepicker')) {
                $el.datepicker('setDate', dateObj);
            } else if (dateObj && typeof $.datepicker !== 'undefined') {
                try {
                    $el.val($.datepicker.formatDate(dateFormat || 'yy-mm-dd', dateObj));
                } catch (e) {
                    $el.val(String(rawVal));
                }
            } else {
                $el.val(String(rawVal));
            }

            if (typeof onFormatted === 'function') {
                onFormatted($el.val());
            }
        });
    }

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

                    var initialVal = ko.unwrap(value) || $el.val();
                    if (initialVal) {
                        setDateOnElement($el, initialVal, config.dateFormat, function (formattedVal) {
                            if (ko.isObservable(value) && formattedVal && formattedVal !== ko.unwrap(value)) {
                                value(formattedVal);
                            }
                        });
                    }

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
            update: function (element, valueAccessor, allBindings) {
                var value = ko.unwrap(valueAccessor());
                var options = allBindings.get('datepickerOptions') || {};
                var $el = $(element);
                if (value === $el.val()) {
                    return;
                }
                if ($el.data('datepicker')) {
                    setDateOnElement($el, value, options.dateFormat, function (formattedVal) {
                        if (ko.isObservable(valueAccessor()) && formattedVal && formattedVal !== value) {
                            valueAccessor()(formattedVal);
                        }
                    });
                } else if (value) {
                    $el.val(value);
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
            this.date = ko.observable($('#pnp_date').val() || '');
            return this;
        },

        initialize: function () {
            this._super();

            var self = this;

            var openHandler = function () {
                self.openModal();
            };

            window.DhlPolandPnp = {
                openModal: openHandler
            };

            $('#dhlpoland-pnp-modal').data('openPnpModal', openHandler);

            return this;
        },

        openModal: function () {
            var self = this;

            if ($('#pnp_date').data('datepicker')) {
                $('#pnp_date').datepicker('option', 'minDate', null);
                $('#pnp_date').datepicker('option', 'maxDate', new Date());
                $('#pnp_date').datepicker('setDate', new Date());
                $('#pnp_date').next('.ui-datepicker-trigger').addClass('v-middle').empty();
                if ($('#pnp_date').val()) {
                    self.date($('#pnp_date').val());
                }
            }

            var $modal = $('#dhlpoland-pnp-modal');
            if (!$modal.data('mageModal')) {
                $modal.modal({
                    title: $t('PNP Report'),
                    type: 'slide',
                    buttons: [{
                        text: $t('Generate Report'),
                        class: 'action-primary action-accept',
                        click: function () {
                            $('#dhlpoland-pnp-form').submit();
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
