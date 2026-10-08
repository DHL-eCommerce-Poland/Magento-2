define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/alert',
    'Ideo_Dhl/js/utils/safe-message-content',
    'mage/translate',
    'mage/validation',
    'mage/loader'
], function ($, ko, Component, modal, alert, createSafeMessageContent) {
    'use strict';

    if ($.validator && !$.validator.methods['validate-no-comma']) {
        $.validator.addMethod(
            'validate-no-comma',
            function (value) {
                if (value === null || value === undefined || value === '') {
                    return true;
                }
                return String(value).indexOf(',') === -1;
            },
            $.mage.__('Please use a dot (.) instead of a comma (,) as decimal separator.')
        );
    }

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
                    $el.val(value || '');
                }
            }
        };
    }

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

    function formatDecimal4(val) {
        if (val === null || val === undefined || val === '') return '';
        var str = String(val).replace(',', '.');
        var num = parseFloat(str);
        return isNaN(num) ? '' : num.toFixed(4);
    }

    function formatMoney(val) {
        if (val === null || val === undefined || val === '') return '';
        var str = String(val).replace(',', '.');
        var num = parseFloat(str);
        return isNaN(num) ? '' : num.toFixed(2);
    }

    function isTruthy(val) {
        if (typeof val === 'boolean') return val;
        if (typeof val === 'number') return val > 0;
        if (typeof val === 'string') {
            val = val.trim().toLowerCase();
            return val === '1' || val === 'true' || val === 'yes' || val === 'on';
        }
        return false;
    }

    function createPackageObservable(p) {
        p = p || {};
        var pkgType = p.packageType || p.package_type || 'PACKAGE';
        var pkgTypeObs = ko.observable(pkgType);
        var isEuro = p.hasEuroReturn !== undefined ? p.hasEuroReturn : p.has_euro_return;
        var euroReturnObs = ko.observable(pkgTypeObs() === 'PALLET' ? isTruthy(isEuro) : false);

        var isNonStd = p.isNonStandard !== undefined ? p.isNonStandard : p.is_non_standard;
        var qtyVal = (p.quantity !== undefined && p.quantity !== null && p.quantity !== '') ? String(p.quantity).replace(',', '.') : '';

        var weightObs = ko.observable(formatDecimal4(p.weight));
        var widthObs = ko.observable(formatDecimal4(p.width));
        var lengthObs = ko.observable(formatDecimal4(p.length));
        var heightObs = ko.observable(formatDecimal4(p.height));

        pkgTypeObs.subscribe(function (newType) {
            if (newType !== 'PALLET') {
                euroReturnObs(false);
            }
            if (newType === 'ENVELOPE') {
                weightObs('');
                widthObs('');
                lengthObs('');
                heightObs('');
            }
        });

        return {
            packageType: pkgTypeObs,
            quantity: ko.observable(qtyVal),
            weight: weightObs,
            width: widthObs,
            length: lengthObs,
            height: heightObs,
            isNonStandard: ko.observable(isTruthy(isNonStd)),
            hasEuroReturn: euroReturnObs
        };
    }

    function createCustomsItemObservable(item) {
        item = item || {};
        var valStr = (item.value !== undefined && item.value !== null && item.value !== '') ? String(item.value).replace(',', '.') : '';
        var qtyStr = (item.quantity !== undefined && item.quantity !== null && item.quantity !== '') ? String(item.quantity).replace(',', '.') : '';
        return {
            namePl: ko.observable(item.namePl || ''),
            nameEn: ko.observable(item.nameEn || ''),
            quantity: ko.observable(qtyStr),
            weight: ko.observable(formatDecimal4(item.weight)),
            value: ko.observable(valStr),
            tariffCode: ko.observable(item.tariffCode || ''),
            countryOfOrigin: ko.observable(item.countryOfOrigin || '')
        };
    }

    function createCustomsData(c) {
        c = c || {};
        var itemsRaw = c.customsItems || [];
        var itemsObsArr = ko.observableArray([]);
        if (Array.isArray(itemsRaw) && itemsRaw.length > 0) {
            $.each(itemsRaw, function (i, itemData) {
                itemsObsArr.push(createCustomsItemObservable(itemData));
            });
        }

        var customsTypeObs = ko.observable(c.customsType || 'U');
        var currencyObs = ko.observable(c.currency || 'EUR');
        var categoryOfItemObs = ko.observable(c.categoryOfItem || '11');

        customsTypeObs.subscribe(function (newType) {
            var allowedCurrencies = {
                'I': ['GBP', 'EUR'],
                'U': ['GBP', 'EUR', 'USD', 'PLN', 'CHF'],
                'S': ['GBP', 'EUR', 'USD']
            };
            var allowed = allowedCurrencies[newType];
            if (allowed && allowed.indexOf(currencyObs()) === -1) {
                currencyObs(allowed[0]);
            }

            var allowedCategories = {
                'I': ['9', '11', '21', '32'],
                'U': ['9', '11', '21', '31', '32', '91'],
                'S': ['9', '11', '21', '31', '32', '91']
            };
            var allowedCats = allowedCategories[newType] || ['9', '11', '21', '31', '32', '91'];
            if (allowedCats.indexOf(categoryOfItemObs()) === -1) {
                categoryOfItemObs('11');
            }

            if (newType !== 'I' && customsObj && typeof customsObj.invoiceFile === 'function') {
                customsObj.invoiceFile('');
                if (typeof customsObj.invoiceFileName === 'function') {
                    customsObj.invoiceFileName('');
                }
            }
        });

        var customsObj = {
            selectedCustomsTemplateId: ko.observable(c.selectedCustomsTemplateId || null),
            customsType: customsTypeObs,
            categoryOfItem: categoryOfItemObs,
            currency: currencyObs,
            countryOfOrigin: ko.observable(c.countryOfOrigin || 'PL'),
            defaultTariffCode: ko.observable(c.defaultTariffCode || ''),
            costsOfShipment: ko.observable(c.costsOfShipment !== undefined && c.costsOfShipment !== null ? c.costsOfShipment : ''),
            grossWeight: ko.observable(c.grossWeight !== undefined && c.grossWeight !== null ? c.grossWeight : ''),
            firstName: ko.observable(c.firstName || ''),
            secondaryName: ko.observable(c.secondaryName || ''),
            nipNr: ko.observable(c.nipNr || ''),
            eoriNr: ko.observable(c.eoriNr || ''),
            vatRegistrationNumber: ko.observable(c.vatRegistrationNumber || ''),
            eoriNrReceiver: ko.observable(c.eoriNrReceiver || ''),
            vatRegistrationNumberReceiver: ko.observable(c.vatRegistrationNumberReceiver || ''),
            invoiceNr: ko.observable(c.invoiceNr || ''),
            invoiceDate: ko.observable(c.invoiceDate || ''),
            invoiceFile: ko.observable(c.invoiceFile || ''),
            invoiceFileName: ko.observable(c.invoiceFileName || ''),
            additionalInfo: ko.observable(c.additionalInfo || ''),
            notExceedValue: ko.observable(c.notExceedValue !== undefined ? isTruthy(c.notExceedValue) : true),
            notProhibitedGoods: ko.observable(c.notProhibitedGoods !== undefined ? isTruthy(c.notProhibitedGoods) : true),
            notRestrictedGoods: ko.observable(c.notRestrictedGoods !== undefined ? isTruthy(c.notRestrictedGoods) : true),
            invoiceContent: ko.observable(c.invoiceContent !== undefined ? isTruthy(c.invoiceContent) : true),
            customsItems: itemsObsArr
        };

        customsObj.addCustomsItemRow = function () {
            customsObj.customsItems.push(createCustomsItemObservable({
                namePl: '',
                nameEn: '',
                quantity: '',
                weight: '',
                value: '',
                tariffCode: '',
                countryOfOrigin: ''
            }));
        };

        customsObj.removeCustomsItemRow = function (index) {
            customsObj.customsItems.splice(index, 1);
        };

        return customsObj;
    }

    function createShipmentData(data, todayDate) {
        data = data || {};
        var s = data.sender || {};
        var r = data.recipient || {};
        var ss = data.specialServices || {};
        var st = data.shipmentSettings || {};
        var defaultPointId = s.selectedDropOffPointId || data.defaultDropOffPointId || null;
        var defaultServiceId = st.selectedDeliveryServiceId || data.defaultDeliveryServiceId || null;

        var senderCountry = (s.country && s.country.length === 2) ? s.country.toUpperCase() : 'PL';
        var recipientCountry = (r.country && r.country.length === 2) ? r.country.toUpperCase() : 'PL';

        var pFrom = s.pickupHourFrom || s.pickup_time_from || st.pickupHourFrom || '';
        var pTo = s.pickupHourTo || s.pickup_time_to || st.pickupHourTo || '';
        var pickupHourFromObs = ko.observable(pFrom);
        var pickupHourToObs = ko.observable(pTo);

        var initialRecName = r.name || '';
        var initialRecType = r.type || (initialRecName.trim() !== '' ? 'B' : 'C');

        var recipientObj = {
            type: ko.observable(initialRecType),
            name: ko.observable(initialRecName),
            firstName: ko.observable(r.firstName || ''),
            lastName: ko.observable(r.lastName || ''),
            contactPerson: ko.observable(r.contactPerson || ''),
            street: ko.observable(r.street || ''),
            houseNumber: ko.observable(r.houseNumber || ''),
            flatNumber: ko.observable(r.flatNumber || ''),
            postalCode: ko.observable(r.postalCode || ''),
            city: ko.observable(r.city || ''),
            country: ko.observable(recipientCountry),
            popId: ko.observable(r.popId || ''),
            popName: ko.observable(r.popName || ''),
            popStreet: ko.observable(r.popStreet || ''),
            popCity: ko.observable(r.popCity || ''),
            popPostcode: ko.observable(r.popPostcode || ''),
            postnummer: ko.observable(r.postnummer || ''),
            mapPointType: ko.observable(r.mapPointType || 'courier'),
            phone: ko.observable(r.phone || ''),
            email: ko.observable(r.email || '')
        };

        recipientObj.mapPointType.subscribe(function (newType) {
            if (newType === 'courier') {
                recipientObj.popId('');
                recipientObj.popName('');
                recipientObj.popStreet('');
                recipientObj.popCity('');
                recipientObj.popPostcode('');
                recipientObj.postnummer('');
            }
        });

        if (recipientObj.mapPointType() === 'courier') {
            recipientObj.popId('');
            recipientObj.popName('');
            recipientObj.popStreet('');
            recipientObj.popCity('');
            recipientObj.popPostcode('');
            recipientObj.postnummer('');
        }

        recipientObj.name.subscribe(function (newName) {
            if (newName && newName.trim() !== '' && recipientObj.type() !== 'B') {
                recipientObj.type('B');
            }
        });

        var packagesRaw = st.packages || [];
        var packagesObsArr = ko.observableArray([]);

        if (Array.isArray(packagesRaw) && packagesRaw.length > 0) {
            $.each(packagesRaw, function (i, pData) {
                packagesObsArr.push(createPackageObservable(pData));
            });
        }

        var todayStr = todayDate;
        var dDate = st.shipmentDate || todayStr;
        if (dDate < todayStr) {
            dDate = todayStr;
        }

        var shipmentSettingsObj = {
            selectedDeliveryServiceId: ko.observable(defaultServiceId),
            serviceType: ko.observable(st.serviceType || ''),
            shipmentDate: ko.observable(dDate),
            content: ko.observable(st.content !== undefined ? st.content : ''),
            mpk: ko.observable(st.mpk || ''),
            comment: ko.observable(st.comment || ''),
            reference: ko.observable(st.reference || ''),
            pickupHourFrom: pickupHourFromObs,
            pickupHourTo: pickupHourToObs,
            labelType: ko.observable(st.labelType || 'BLP'),
            autoCreateReturnShipment: ko.observable(isTruthy(st.autoCreateReturnShipment)),
            returnLabelType: ko.observable(st.returnLabelType || 'BLP'),
            returnQrCodeType: ko.observable(st.returnQrCodeType || 'QR_PDF'),
            returnLabelExpDays: ko.observable(st.returnLabelExpDays && parseInt(st.returnLabelExpDays, 10) > 0 ? parseInt(st.returnLabelExpDays, 10) : 14),
            returnBookCourier: ko.observable(st.returnBookCourier !== undefined && st.returnBookCourier !== null ? st.returnBookCourier : 0),
            addressType: ko.observable(st.addressType || 'N'),
            packages: packagesObsArr
        };

        shipmentSettingsObj.autoCreateReturnShipment.subscribe(function (enabled) {
            if (enabled && (!shipmentSettingsObj.returnLabelExpDays() || parseInt(shipmentSettingsObj.returnLabelExpDays(), 10) <= 0)) {
                shipmentSettingsObj.returnLabelExpDays(14);
            }
        });

        shipmentSettingsObj.addPackageRow = function () {
            shipmentSettingsObj.packages.push(createPackageObservable({
                packageType: 'PACKAGE',
                quantity: '',
                weight: '',
                width: '',
                length: '',
                height: '',
                isNonStandard: false,
                hasEuroReturn: false
            }));
        };

        shipmentSettingsObj.removePackageRow = function (index) {
            shipmentSettingsObj.packages.splice(index, 1);
        };

        var orderGrandTotalVal = (data.orderGrandTotal !== undefined && data.orderGrandTotal !== null && data.orderGrandTotal !== '')
            ? formatMoney(data.orderGrandTotal)
            : '';
        var orderGrandTotalObs = ko.observable(orderGrandTotalVal);

        var initialInsurance = isTruthy(ss.insurance);
        var initialInsuranceValue = (ss.insuranceValue !== undefined && ss.insuranceValue !== null && ss.insuranceValue !== '')
            ? formatMoney(ss.insuranceValue)
            : (initialInsurance ? orderGrandTotalVal : '');

        var initialCod = isTruthy(ss.collectOnDelivery);
        var initialCodAmountRaw = ss.collectOnDeliveryValue;
        var initialCodAmount = (initialCodAmountRaw !== undefined && initialCodAmountRaw !== null && initialCodAmountRaw !== '')
            ? formatMoney(initialCodAmountRaw)
            : (initialCod ? orderGrandTotalVal : '');

        var insuranceObs = ko.observable(initialInsurance);
        var insuranceValueObs = ko.observable(initialInsuranceValue);
        var codObs = ko.observable(initialCod);
        var codAmountObs = ko.observable(initialCodAmount);
        var eveningDeliveryObs = ko.observable(ss.deliveryHours === '1722' || isTruthy(ss.deliveryEvening));
        var saturdayDeliveryObs = ko.observable(isTruthy(ss.deliveryOnSaturday));
        var saturdayDispatchObs = ko.observable(isTruthy(ss.pickupOnSaturday));
        var pdiObs = ko.observable(isTruthy(ss.predeliveryInformation));
        var rodObs = ko.observable(isTruthy(ss.returnOnDelivery));
        var rodDocumentTextVal = (ss.returnOnDeliveryReference !== undefined && ss.returnOnDeliveryReference !== null)
            ? ss.returnOnDeliveryReference
            : '';
        var rodDocumentTextObs = ko.observable(rodDocumentTextVal);
        var podObs = ko.observable(isTruthy(ss.proofOfDelivery));
        var ageverObs = ko.observable(isTruthy(ss.ageVer));

        insuranceObs.subscribe(function (checked) {
            if (checked) {
                if (!insuranceValueObs() || String(insuranceValueObs()).trim() === '') {
                    insuranceValueObs(orderGrandTotalObs() || '');
                }
            }
        });

        codObs.subscribe(function (checked) {
            if (checked) {
                if (!codAmountObs() || String(codAmountObs()).trim() === '') {
                    codAmountObs(orderGrandTotalObs() || '');
                }
            }
        });

        return {
            orderId: data.orderId || null,
            orderGrandTotal: orderGrandTotalObs,
            sender: {
                selectedDropOffPointId: ko.observable(defaultPointId),
                sapNumber: ko.observable(s.sapNumber || ''),
                name: ko.observable(s.name || ''),
                firstName: ko.observable(s.firstName || ''),
                lastName: ko.observable(s.lastName || ''),
                street: ko.observable(s.street || ''),
                houseNumber: ko.observable(s.houseNumber || ''),
                flatNumber: ko.observable(s.flatNumber || ''),
                postalCode: ko.observable(s.postalCode || ''),
                city: ko.observable(s.city || ''),
                country: ko.observable(senderCountry),
                phone: ko.observable(s.phone || ''),
                email: ko.observable(s.email || ''),
                pickupHourFrom: shipmentSettingsObj.pickupHourFrom,
                pickupHourTo: shipmentSettingsObj.pickupHourTo
            },
            recipient: recipientObj,
            specialServices: {
                insurance: insuranceObs,
                insuranceValue: insuranceValueObs,
                collectOnDelivery: codObs,
                collectOnDeliveryValue: codAmountObs,
                deliveryEvening: eveningDeliveryObs,
                deliveryOnSaturday: saturdayDeliveryObs,
                pickupOnSaturday: saturdayDispatchObs,
                predeliveryInformation: pdiObs,
                returnOnDelivery: rodObs,
                returnOnDeliveryReference: rodDocumentTextObs,
                proofOfDelivery: podObs,
                ageVer: ageverObs
            },
            shipmentSettings: shipmentSettingsObj,
            customs: createCustomsData(data.customs || {})
        };
    }

    function showAdminMessage(type, messageText) {
        var $messages = $('#messages');
        if ($messages.length === 0) {
            $messages = $('<div id="messages"><div class="messages"></div></div>');
            $('.page-main-actions').after($messages);
        }
        var cssClass = (type === 'success') ? 'message-success success' : 'message-error error';
        var $message = $('<div/>')
            .addClass('message ' + cssClass + ' message-dismissible')
            .attr('data-ui-id', 'messages-message-' + type);
        $message.append(createSafeMessageContent(messageText));
        $messages.find('.messages').empty().append($message);
        $('html, body').animate({ scrollTop: 0 }, 'fast');
    }

    return Component.extend({
        defaults: {
            saveUrl: '',
            loadTemplateUrl: '',
            todayDate: ''
        },

        initialize: function () {
            this._super();

            this.shipmentData = ko.observable(createShipmentData({}, this.todayDate));
            this.senderTemplates = ko.observableArray([]);
            this.countryOptions = ko.observableArray([]);
            this.customsItemCountryOptions = ko.observableArray([{ value: '', label: '' }]);
            this.shipmentTemplates = ko.observableArray([]);
            this.serviceCodeOptions = ko.observableArray([]);
            this.labelTypeOptions = ko.observableArray([]);
            this.packageTypeOptions = ko.observableArray([]);
            this.addressTypeOptions = ko.observableArray([]);
            this.shipmentAddressTypeOptions = ko.observableArray([]);
            this.returnQrCodeTypeOptions = ko.observableArray([]);
            this.returnBookCourierOptions = ko.observableArray([]);
            this.customsTemplates = ko.observableArray([]);
            this.customsTypeOptions = ko.observableArray([]);
            this.categoryOfItemOptions = ko.observableArray([]);
            this.customsCurrencyOptions = ko.observableArray([]);
            this.internationalMapPointTypeOptions = ko.observableArray([
                { value: 'courier', label: $.mage.__('Courier') },
                { value: 'packStation', label: $.mage.__('Packstation') },
                { value: 'parcelShop', label: $.mage.__('Parcelshop') }
            ]);

            var self = this;

            this.initMapMessageListener();

            this.isPointServiceSelected = ko.pureComputed(function () {
                var draft = self.shipmentData();
                if (!draft || !draft.shipmentSettings || typeof draft.shipmentSettings.serviceType !== 'function') {
                    return false;
                }
                var sType = (draft.shipmentSettings.serviceType() || '').toUpperCase();
                return sType === 'SP' || sType === 'EK';
            });
            this.isSpServiceSelected = this.isPointServiceSelected;

            this.isCustomsClearanceVisible = ko.pureComputed(function () {
                var draft = self.shipmentData();
                if (!draft || !draft.recipient || typeof draft.recipient.country !== 'function') {
                    return false;
                }
                var country = draft.recipient.country();
                return country && country.toUpperCase() !== 'PL';
            });

            var defaultCategoryOptions = [
                { value: '9', label: $.mage.__('Other (9)') },
                { value: '11', label: $.mage.__('Sales of Goods (11)') },
                { value: '21', label: $.mage.__('Return of Goods (21)') },
                { value: '31', label: $.mage.__('Gift (31)') },
                { value: '32', label: $.mage.__('Patterns and Samples (32)') },
                { value: '91', label: $.mage.__('Documents (91)') }
            ];

            this.filteredCurrencyOptions = ko.pureComputed(function () {
                var allCurrencies = self.customsCurrencyOptions();
                var draft = self.shipmentData();
                if (!draft || !draft.customs || !draft.customs.customsType) {
                    return allCurrencies;
                }
                var cType = draft.customs.customsType;
                var type = typeof cType === 'function' ? cType() : cType;
                var allowedCurrencies = {
                    'I': ['GBP', 'EUR'],
                    'U': ['GBP', 'EUR', 'USD', 'PLN', 'CHF'],
                    'S': ['GBP', 'EUR', 'USD']
                };
                var allowed = allowedCurrencies[type] || ['GBP', 'EUR', 'USD', 'PLN', 'CHF'];
                return ko.utils.arrayFilter(allCurrencies, function (curr) {
                    return curr && allowed.indexOf(curr.value) !== -1;
                });
            });

            this.filteredCategoryOfItemOptions = ko.pureComputed(function () {
                var allCategories = self.categoryOfItemOptions();
                if (!allCategories || !allCategories.length) {
                    allCategories = defaultCategoryOptions;
                }
                var draft = self.shipmentData();
                if (!draft || !draft.customs || !draft.customs.customsType) {
                    return allCategories;
                }
                var cType = draft.customs.customsType;
                var type = typeof cType === 'function' ? cType() : cType;
                var allowedCategories = {
                    'I': ['9', '11', '21', '32'],
                    'U': ['9', '11', '21', '31', '32', '91'],
                    'S': ['9', '11', '21', '31', '32', '91']
                };
                var allowed = allowedCategories[type] || ['9', '11', '21', '31', '32', '91'];
                return ko.utils.arrayFilter(allCategories, function (cat) {
                    return cat && allowed.indexOf(String(cat.value)) !== -1;
                });
            });

            window.ideoDhlOpenPrepareModal = function (orderId, prepareUrl) {
                self.openModal(orderId, prepareUrl);
            };

            return this;
        },

        initMapMessageListener: function () {
            var self = this;
            if (this._mapMessageListener) {
                return;
            }
            this._mapMessageListener = function (event) {
                var iframe = document.getElementById('ideo-dhl-prepare-map-iframe');
                if (!event || event.origin !== 'https://parcelshop.dhl.pl' || !iframe
                    || event.source !== iframe.contentWindow || !self._activeMapModal || !event.data) {
                    return;
                }
                var pointData = event.data;
                if (typeof pointData === 'string') {
                    try {
                        pointData = JSON.parse(pointData);
                    } catch (e) {
                        return;
                    }
                }
                if (pointData && typeof pointData === 'object' && pointData.sap) {
                    var draft = self.shipmentData();
                    if (draft && draft.recipient) {
                        var nameStr = pointData.name || '';
                        var addrStr = pointData.address || '';
                        if (!addrStr && (pointData.street || pointData.city)) {
                            var streetStr = $.trim((pointData.street || '') + ' ' + (pointData.houseNo || pointData.streetNo || ''));
                            var cityStr = $.trim((pointData.zip || pointData.postalCode || '') + ' ' + (pointData.city || ''));
                            addrStr = $.trim(streetStr + (streetStr && cityStr ? ', ' : '') + cityStr);
                        }
                        var popName = (nameStr && addrStr && nameStr !== addrStr) ? (nameStr + ' - ' + addrStr) : (nameStr || addrStr || pointData.sap || '');

                        var streetBase = $.trim(pointData.street || '');
                        var houseNo = $.trim(pointData.houseNo || pointData.streetNo || '');
                        var streetWithHouse = streetBase;
                        if (houseNo && streetBase && streetBase.indexOf(houseNo) === -1) {
                            streetWithHouse = streetBase + ' ' + houseNo;
                        } else if (!streetBase && houseNo) {
                            streetWithHouse = houseNo;
                        }
                        var isGenericPrefix = /^(packstation|postfiliale)\b/i.test(nameStr);
                        var popStreet = (nameStr && streetWithHouse && nameStr !== streetWithHouse && !isGenericPrefix)
                            ? (nameStr + ' - ' + streetWithHouse)
                            : (streetWithHouse || nameStr);

                        if (typeof draft.recipient.popId === 'function') draft.recipient.popId(pointData.sap || '');
                        if (typeof draft.recipient.popName === 'function') draft.recipient.popName(popName);
                        if (typeof draft.recipient.popStreet === 'function') draft.recipient.popStreet(popStreet);
                        if (typeof draft.recipient.popPostcode === 'function') draft.recipient.popPostcode(pointData.zip || pointData.postalCode || '');
                        if (typeof draft.recipient.popCity === 'function') draft.recipient.popCity(pointData.city || '');
                        if (pointData.postnummer && typeof draft.recipient.postnummer === 'function') {
                            draft.recipient.postnummer(pointData.postnummer);
                        }
                    }
                    if (self._activeMapModal) {
                        self._activeMapModal.closeModal();
                    }
                }
            };

            if (window.addEventListener) {
                window.addEventListener('message', this._mapMessageListener, false);
            } else if (window.attachEvent) {
                window.attachEvent('onmessage', this._mapMessageListener);
            }
        },

        openPointMapModal: function () {
            var self = this;
            var draft = this.shipmentData();
            if (!draft || !draft.recipient) {
                return;
            }
            var country = (typeof draft.recipient.country === 'function' ? draft.recipient.country() : draft.recipient.country) || 'PL';
            country = String(country).toUpperCase();
            var baseUrl = 'https://parcelshop.dhl.pl/mapa';
            var params = [];

            if (country !== 'PL') {
                params.push('country=' + encodeURIComponent(country));
                var pType = typeof draft.recipient.mapPointType === 'function' ? draft.recipient.mapPointType() : draft.recipient.mapPointType;
                pType = pType || 'courier';
                params.push('ptype=' + encodeURIComponent(pType));
            }

            var iframeUrl = params.length ? baseUrl + '?' + params.join('&') : baseUrl;

            var $modalContainer = $('#ideo-dhl-prepare-map-modal-container');
            if (!$modalContainer.length) {
                $modalContainer = $('<div id="ideo-dhl-prepare-map-modal-container" style="display:none;"><iframe id="ideo-dhl-prepare-map-iframe" style="width:100%; height:80vh; min-height:500px; border:none;"></iframe></div>');
                $('body').append($modalContainer);
            }

            $modalContainer.find('iframe').attr('src', iframeUrl);

            var options = {
                type: 'popup',
                responsive: true,
                innerScroll: false,
                title: $.mage.__('Select DHL Delivery Point'),
                modalClass: 'dhl-map-modal-popup',
                buttons: [],
                closed: function () {
                    $modalContainer.find('iframe').attr('src', '');
                    self._activeMapModal = null;
                }
            };

            if (!$modalContainer.data('mageModal')) {
                modal(options, $modalContainer);
            }
            $modalContainer.modal('openModal');
            self._activeMapModal = $modalContainer.data('mageModal');
        },

        addPackageRow: function () {
            var settings = this.shipmentData().shipmentSettings;
            if (settings && typeof settings.addPackageRow === 'function') {
                settings.addPackageRow();
            }
        },

        removePackageRow: function (index) {
            var settings = this.shipmentData().shipmentSettings;
            if (settings && typeof settings.removePackageRow === 'function') {
                settings.removePackageRow(index);
            }
        },

        addCustomsItemRow: function () {
            var customs = this.shipmentData() ? this.shipmentData().customs : null;
            if (customs && typeof customs.addCustomsItemRow === 'function') {
                customs.addCustomsItemRow();
            }
        },

        removeCustomsItemRow: function (index) {
            var customs = this.shipmentData() ? this.shipmentData().customs : null;
            if (customs && typeof customs.removeCustomsItemRow === 'function') {
                customs.removeCustomsItemRow(index);
            }
        },

        onInvoiceFileSelected: function (event) {
            var files = event.target ? event.target.files : null;
            if (!files || !files.length) {
                return;
            }
            var file = files[0];
            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                alert({
                    title: $.mage.__('Error'),
                    content: $.mage.__('Only PDF files are allowed for VAT Invoice.')
                });
                event.target.value = '';
                return;
            }

            var maxSizeBytes = 3 * 1024 * 1024; // 3MB limit
            if (file.size > maxSizeBytes) {
                alert({
                    title: $.mage.__('Error'),
                    content: $.mage.__('File size exceeds the 3MB limit.')
                });
                event.target.value = '';
                return;
            }

            var self = this;
            var reader = new FileReader();
            reader.onload = function (e) {
                var result = e.target.result || '';
                var base64 = result.indexOf(',') !== -1 ? result.split(',')[1] : result;
                var customs = self.shipmentData() ? self.shipmentData().customs : null;
                if (customs) {
                    customs.invoiceFile(base64);
                    if (typeof customs.invoiceFileName === 'function') {
                        customs.invoiceFileName(file.name);
                    }
                }
                event.target.value = '';
            };
            reader.onerror = function () {
                alert({
                    title: $.mage.__('Error'),
                    content: $.mage.__('Failed to read PDF file.')
                });
                event.target.value = '';
            };
            reader.readAsDataURL(file);
        },

        resetCustomsData: function () {
            var customs = this.shipmentData() ? this.shipmentData().customs : null;
            if (!customs) return;

            this._customsTemplateMatchRequestId = (this._customsTemplateMatchRequestId || 0) + 1;
            customs.selectedCustomsTemplateId(null);
            customs.customsType('U');
            customs.categoryOfItem('11');
            customs.currency('EUR');
            customs.countryOfOrigin('PL');
            customs.defaultTariffCode('');
            customs.costsOfShipment('');
            customs.grossWeight('');
            customs.firstName('');
            customs.secondaryName('');
            customs.nipNr('');
            customs.eoriNr('');
            customs.vatRegistrationNumber('');
            customs.eoriNrReceiver('');
            customs.vatRegistrationNumberReceiver('');
            customs.invoiceNr('');
            customs.invoiceDate('');
            customs.invoiceFile('');
            customs.additionalInfo('');
            customs.notExceedValue(true);
            customs.notProhibitedGoods(true);
            customs.notRestrictedGoods(true);
            customs.invoiceContent(true);
            if (customs.customsItems) {
                customs.customsItems.removeAll();
            }
            this._activeCustomsTemplateId = null;
        },

        populateCustomsData: function (data) {
            var customs = this.shipmentData() ? this.shipmentData().customs : null;
            if (!customs || !data) return;

            if (data.customsType !== undefined) customs.customsType(data.customsType);
            if (data.categoryOfItem !== undefined) customs.categoryOfItem(data.categoryOfItem);
            if (data.currency !== undefined) customs.currency(data.currency);
            if (data.countryOfOrigin !== undefined) customs.countryOfOrigin(data.countryOfOrigin);
            if (data.defaultTariffCode !== undefined) customs.defaultTariffCode(data.defaultTariffCode);
            if (data.costsOfShipment !== undefined && data.costsOfShipment !== null) customs.costsOfShipment(data.costsOfShipment);
            if (data.grossWeight !== undefined && data.grossWeight !== null) customs.grossWeight(data.grossWeight);
            if (data.firstName !== undefined) customs.firstName(data.firstName);
            if (data.secondaryName !== undefined) customs.secondaryName(data.secondaryName);
            if (data.nipNr !== undefined) customs.nipNr(data.nipNr);
            if (data.eoriNr !== undefined) customs.eoriNr(data.eoriNr);
            if (data.vatRegistrationNumber !== undefined) customs.vatRegistrationNumber(data.vatRegistrationNumber);
            if (data.eoriNrReceiver !== undefined) customs.eoriNrReceiver(data.eoriNrReceiver);
            if (data.vatRegistrationNumberReceiver !== undefined) customs.vatRegistrationNumberReceiver(data.vatRegistrationNumberReceiver);
            if (data.invoiceNr !== undefined) customs.invoiceNr(data.invoiceNr);
            if (data.invoiceDate !== undefined) customs.invoiceDate(data.invoiceDate);
            if (data.invoiceFile !== undefined) customs.invoiceFile(data.invoiceFile);
            if (data.additionalInfo !== undefined) customs.additionalInfo(data.additionalInfo);
            if (data.notExceedValue !== undefined) customs.notExceedValue(isTruthy(data.notExceedValue));
            if (data.notProhibitedGoods !== undefined) customs.notProhibitedGoods(isTruthy(data.notProhibitedGoods));
            if (data.notRestrictedGoods !== undefined) customs.notRestrictedGoods(isTruthy(data.notRestrictedGoods));
            if (data.customsType === 'I') {
                customs.invoiceContent(data.invoiceContent !== undefined ? isTruthy(data.invoiceContent) : true);
            } else if (data.customsType !== undefined) {
                customs.invoiceContent(false);
            }

            if (customs.customsItems) {
                customs.customsItems.removeAll();
                if (Array.isArray(data.customsItems) && data.customsItems.length > 0) {
                    $.each(data.customsItems, function (i, item) {
                        customs.customsItems.push(createCustomsItemObservable(item));
                    });
                }
            }
        },

        onCustomsTemplateChange: function (templateId, event) {
            var customs = this.shipmentData() ? this.shipmentData().customs : null;
            if (!customs) return;

            if (event && event.target) {
                this._customsTemplateMatchRequestId = (this._customsTemplateMatchRequestId || 0) + 1;
                templateId = event.target.value;
            } else if (templateId === undefined || templateId === null || typeof templateId === 'object') {
                templateId = (typeof customs.selectedCustomsTemplateId === 'function')
                    ? customs.selectedCustomsTemplateId()
                    : null;
            }

            templateId = (templateId !== null && templateId !== undefined) ? String(templateId).trim() : '';

            if (typeof customs.selectedCustomsTemplateId === 'function') {
                var currentObsVal = customs.selectedCustomsTemplateId();
                var parsedVal = templateId ? (isNaN(Number(templateId)) ? templateId : Number(templateId)) : null;
                if (currentObsVal != parsedVal) {
                    customs.selectedCustomsTemplateId(parsedVal);
                }
            }

            if (this._activeCustomsTemplateId === templateId) {
                return;
            }
            this._activeCustomsTemplateId = templateId;

            if (!templateId) {
                this.resetCustomsData();
                return;
            }

            var url = this.loadTemplateUrl;
            if (!url) return;

            var self = this;
            $.getJSON(url, {
                template_id: templateId,
                template_type: 'customs',
                order_id: self.shipmentData().orderId
            }).done(function (res) {
                if (self._activeCustomsTemplateId === templateId && res.success && res.data) {
                    self.populateCustomsData(res.data);
                }
            });
        },

        onRecipientCountryChange: function (countryId) {
            var customs = this.shipmentData() ? this.shipmentData().customs : null,
                country = countryId ? String(countryId).trim().toUpperCase() : '',
                url = this.loadTemplateUrl,
                self = this,
                requestId;

            if (!customs) return;

            this.resetCustomsData();
            if (country === '' || country === 'PL' || !url) {
                return;
            }

            requestId = this._customsTemplateMatchRequestId;
            $.getJSON(url, {
                template_type: 'customs',
                country_id: country,
                order_id: this.shipmentData().orderId
            }).done(function (response) {
                var templateId;

                if (requestId !== self._customsTemplateMatchRequestId || !response.success || !response.data) {
                    return;
                }

                templateId = response.data.selectedCustomsTemplateId;
                if (!templateId) {
                    return;
                }

                self._activeCustomsTemplateId = String(templateId);
                customs.selectedCustomsTemplateId(templateId);
                self.populateCustomsData(response.data);
            });
        },

        onSenderTemplateChange: function (templateId, event) {
            var sender = this.shipmentData() ? this.shipmentData().sender : null;
            if (!sender) return;

            if (event && event.target) {
                templateId = event.target.value;
            } else if (templateId === undefined || templateId === null || typeof templateId === 'object') {
                templateId = (typeof sender.selectedDropOffPointId === 'function')
                    ? sender.selectedDropOffPointId()
                    : null;
            }

            templateId = (templateId !== null && templateId !== undefined) ? String(templateId).trim() : '';

            if (typeof sender.selectedDropOffPointId === 'function') {
                var currentObsVal = sender.selectedDropOffPointId();
                var parsedVal = templateId ? (isNaN(Number(templateId)) ? templateId : Number(templateId)) : null;
                if (currentObsVal != parsedVal) {
                    sender.selectedDropOffPointId(parsedVal);
                }
            }

            if (this._activeSenderTemplateId === templateId) {
                return;
            }
            this._activeSenderTemplateId = templateId;

            if (!templateId) {
                sender.name('');
                sender.firstName('');
                sender.lastName('');
                sender.street('');
                sender.houseNumber('');
                sender.flatNumber('');
                sender.postalCode('');
                sender.city('');
                sender.country('PL');
                sender.phone('');
                sender.email('');
                if (this.shipmentData() && this.shipmentData().shipmentSettings) {
                    if (typeof this.shipmentData().shipmentSettings.pickupHourFrom === 'function') {
                        this.shipmentData().shipmentSettings.pickupHourFrom('');
                    }
                    if (typeof this.shipmentData().shipmentSettings.pickupHourTo === 'function') {
                        this.shipmentData().shipmentSettings.pickupHourTo('');
                    }
                }
                return;
            }

            var url = this.loadTemplateUrl;
            if (!url) return;

            var self = this;
            $.getJSON(url, { template_id: templateId, template_type: 'sender' })
                .done(function (res) {
                    if (res.success && res.data) {
                        var currentSender = self.shipmentData().sender;
                        $.each(res.data, function (key, val) {
                            if (key === 'payer' || key === 'selectedDropOffPointId') return;
                            if (typeof currentSender[key] === 'function') {
                                if (key === 'country') {
                                    val = (val && val.length === 2) ? val.toUpperCase() : 'PL';
                                }
                                currentSender[key](val);
                            }
                        });
                        if (self.shipmentData() && self.shipmentData().shipmentSettings) {
                            if (res.data.pickupHourFrom !== undefined && typeof self.shipmentData().shipmentSettings.pickupHourFrom === 'function') {
                                self.shipmentData().shipmentSettings.pickupHourFrom(res.data.pickupHourFrom || '');
                            }
                            if (res.data.pickupHourTo !== undefined && typeof self.shipmentData().shipmentSettings.pickupHourTo === 'function') {
                                self.shipmentData().shipmentSettings.pickupHourTo(res.data.pickupHourTo || '');
                            }
                        }
                    }
                });
        },

        onSenderPresetChange: function () {
            return this.onSenderTemplateChange();
        },

        onShipmentTemplateChange: function (templateId, event) {
            var settings = this.shipmentData() ? this.shipmentData().shipmentSettings : null;
            if (!settings) return;

            if (event && event.target) {
                templateId = event.target.value;
            } else if (templateId === undefined || templateId === null || typeof templateId === 'object') {
                templateId = (typeof settings.selectedDeliveryServiceId === 'function')
                    ? settings.selectedDeliveryServiceId()
                    : null;
            }

            templateId = (templateId !== null && templateId !== undefined) ? String(templateId).trim() : '';

            if (typeof settings.selectedDeliveryServiceId === 'function') {
                var currentObsVal = settings.selectedDeliveryServiceId();
                var parsedVal = templateId ? (isNaN(Number(templateId)) ? templateId : Number(templateId)) : null;
                if (currentObsVal != parsedVal) {
                    settings.selectedDeliveryServiceId(parsedVal);
                }
            }

            if (this._activeShipmentTemplateId === templateId) {
                return;
            }
            this._activeShipmentTemplateId = templateId;

            if (!templateId) {
                settings.serviceType('');
                settings.content('');
                settings.mpk('');
                settings.comment('');
                settings.reference('');
                settings.labelType('BLP');
                settings.autoCreateReturnShipment(false);
                settings.returnLabelType('BLP');
                settings.returnQrCodeType('QR_PDF');
                settings.returnLabelExpDays(14);
                settings.returnBookCourier(0);
                settings.addressType('N');
                if (settings.packages) {
                    settings.packages.removeAll();
                }
                if (this.shipmentData().specialServices) {
                    var ssReset = this.shipmentData().specialServices;
                    ssReset.insurance(false);
                    ssReset.insuranceValue('');
                    ssReset.collectOnDelivery(false);
                    ssReset.collectOnDeliveryValue('');
                    ssReset.deliveryEvening(false);
                    ssReset.deliveryOnSaturday(false);
                    ssReset.pickupOnSaturday(false);
                    ssReset.predeliveryInformation(false);
                    ssReset.returnOnDelivery(false);
                    ssReset.returnOnDeliveryReference('');
                    ssReset.proofOfDelivery(false);
                    ssReset.ageVer(false);
                }
                return;
            }

            var url = this.loadTemplateUrl;
            if (!url) return;

            var self = this;
            $.getJSON(url, {
                template_id: templateId,
                template_type: 'shipment',
                order_id: self.shipmentData().orderId
            }).done(function (res) {
                if (res.success && res.data) {
                    var shipmentSettings = res.data.shipmentSettings || res.data;
                    var specialServices = res.data.specialServices || res.specialServices;

                    if (shipmentSettings && self.shipmentData().shipmentSettings) {
                        var currentSettings = self.shipmentData().shipmentSettings;
                        $.each(shipmentSettings, function (key, val) {
                            if (key === 'selectedDeliveryServiceId' || key === 'packages') return;
                            if (typeof currentSettings[key] === 'function') {
                                if (typeof val === 'string') {
                                    val = val.trim();
                                }
                                if (key === 'autoCreateReturnShipment') {
                                    val = isTruthy(val);
                                }
                                currentSettings[key](val);
                            }
                        });

                        // Update package rows with template defaults
                        var tplPackages = shipmentSettings.packages || res.data.packages;
                        if (currentSettings.packages) {
                            currentSettings.packages.removeAll();
                            if (Array.isArray(tplPackages) && tplPackages.length > 0) {
                                $.each(tplPackages, function (i, pData) {
                                    currentSettings.packages.push(createPackageObservable(pData));
                                });
                            }
                        }
                    }

                    if (specialServices && self.shipmentData().specialServices) {
                        var currentSS = self.shipmentData().specialServices;
                        var resOrderTotal = (res.data && res.data.orderGrandTotal !== undefined && res.data.orderGrandTotal !== null && res.data.orderGrandTotal !== '')
                            ? formatMoney(res.data.orderGrandTotal)
                            : null;

                        if (resOrderTotal && typeof self.shipmentData().orderGrandTotal === 'function') {
                            self.shipmentData().orderGrandTotal(resOrderTotal);
                        }

                        var orderTotal = (typeof self.shipmentData().orderGrandTotal === 'function' ? self.shipmentData().orderGrandTotal() : self.shipmentData().orderGrandTotal) || resOrderTotal || '';

                        var tplInsuranceVal = (specialServices.insuranceValue !== null && specialServices.insuranceValue !== undefined && specialServices.insuranceValue !== '')
                            ? formatMoney(specialServices.insuranceValue)
                            : '';
                        var tplCodAmt = (specialServices.collectOnDeliveryValue !== null && specialServices.collectOnDeliveryValue !== undefined && specialServices.collectOnDeliveryValue !== '')
                            ? formatMoney(specialServices.collectOnDeliveryValue)
                            : '';

                        if (typeof currentSS.insuranceValue === 'function') {
                            currentSS.insuranceValue(tplInsuranceVal);
                        }
                        if (typeof currentSS.collectOnDeliveryValue === 'function') {
                            currentSS.collectOnDeliveryValue(tplCodAmt);
                        }

                        if (specialServices.returnOnDeliveryReference !== undefined && typeof currentSS.returnOnDeliveryReference === 'function') {
                            currentSS.returnOnDeliveryReference(specialServices.returnOnDeliveryReference || '');
                        }

                        if (specialServices.deliveryEvening !== undefined && typeof currentSS.deliveryEvening === 'function') {
                            currentSS.deliveryEvening(isTruthy(specialServices.deliveryEvening));
                        } else if (specialServices.deliveryHours !== undefined && typeof currentSS.deliveryEvening === 'function') {
                            currentSS.deliveryEvening(specialServices.deliveryHours === '1722');
                        }

                        if (specialServices.deliveryOnSaturday !== undefined && typeof currentSS.deliveryOnSaturday === 'function') {
                            currentSS.deliveryOnSaturday(isTruthy(specialServices.deliveryOnSaturday));
                        }

                        if (specialServices.pickupOnSaturday !== undefined && typeof currentSS.pickupOnSaturday === 'function') {
                            currentSS.pickupOnSaturday(isTruthy(specialServices.pickupOnSaturday));
                        }

                        if (specialServices.predeliveryInformation !== undefined && typeof currentSS.predeliveryInformation === 'function') {
                            currentSS.predeliveryInformation(isTruthy(specialServices.predeliveryInformation));
                        }

                        if (specialServices.returnOnDelivery !== undefined && typeof currentSS.returnOnDelivery === 'function') {
                            currentSS.returnOnDelivery(isTruthy(specialServices.returnOnDelivery));
                        }

                        if (specialServices.proofOfDelivery !== undefined && typeof currentSS.proofOfDelivery === 'function') {
                            currentSS.proofOfDelivery(isTruthy(specialServices.proofOfDelivery));
                        }

                        if (specialServices.ageVer !== undefined && typeof currentSS.ageVer === 'function') {
                            currentSS.ageVer(isTruthy(specialServices.ageVer));
                        }

                        if (specialServices.collectOnDelivery !== undefined && typeof currentSS.collectOnDelivery === 'function') {
                            currentSS.collectOnDelivery(isTruthy(specialServices.collectOnDelivery));
                        }

                        $.each(specialServices, function (key, val) {
                            if (key === 'insuranceValue' || key === 'collectOnDeliveryValue' || key === 'returnOnDeliveryReference') return;
                            if (typeof currentSS[key] === 'function') {
                                currentSS[key](isTruthy(val));
                            }
                        });

                        var isInsChecked = typeof currentSS.insurance === 'function' ? currentSS.insurance() : false;
                        if (isInsChecked && (!currentSS.insuranceValue() || String(currentSS.insuranceValue()).trim() === '')) {
                            currentSS.insuranceValue(orderTotal);
                        }

                        var isCodChecked = typeof currentSS.collectOnDelivery === 'function' && currentSS.collectOnDelivery();
                        if (isCodChecked) {
                            if (typeof currentSS.collectOnDeliveryValue === 'function' && (!currentSS.collectOnDeliveryValue() || String(currentSS.collectOnDeliveryValue()).trim() === '')) {
                                currentSS.collectOnDeliveryValue(orderTotal);
                            }
                        }
                    }

                    // Dynamically update recipient.type based on template addressType
                    var addressType = (shipmentSettings && shipmentSettings.addressType) || (res.data && res.data.addressType) || 'N';
                    if (self.shipmentData().recipient) {
                        if (addressType === 'B') {
                            self.shipmentData().recipient.type('B');
                        } else if (addressType === 'C') {
                            self.shipmentData().recipient.type('C');
                        }
                    }
                }
            });
        },

        onShipmentPresetChange: function () {
            return this.onShipmentTemplateChange();
        },

        openModal: function (orderId, prepareUrl) {
            var self = this;
            if (typeof $('body').loader === 'function') {
                $('body').loader();
            }
            $('body').trigger('processStart');

            $.getJSON(prepareUrl)
                .done(function (response) {
                    if (response.success && response.data) {
                        // 1. Populate dropdown options FIRST
                        self.senderTemplates(response.senderTemplates || []);
                        self.countryOptions(response.countryOptions || []);
                        self.customsItemCountryOptions([{ value: '', label: '' }].concat(response.countryOptions || []));
                        self.shipmentTemplates(response.shipmentTemplates || []);
                        self.serviceCodeOptions(response.serviceCodeOptions || []);
                        self.labelTypeOptions(response.labelTypeOptions || []);
                        self.packageTypeOptions(response.packageTypeOptions || []);
                        self.addressTypeOptions(response.addressTypeOptions || []);
                        self.shipmentAddressTypeOptions(response.shipmentAddressTypeOptions || []);
                        self.returnQrCodeTypeOptions(response.returnQrCodeTypeOptions || []);
                        self.returnBookCourierOptions(response.returnBookCourierOptions || []);
                        self.customsTemplates(response.customsTemplates || []);
                        self.customsTypeOptions(response.customsTypeOptions || []);
                        self.categoryOfItemOptions(response.categoryOfItemOptions || []);
                        self.customsCurrencyOptions(response.customsCurrencyOptions || []);
                        if (response.internationalMapPointTypeOptions && response.internationalMapPointTypeOptions.length) {
                            self.internationalMapPointTypeOptions(response.internationalMapPointTypeOptions);
                        }

                        // 2. Set default IDs if missing
                        var defaultPointId = response.defaultDropOffPointId || (response.data.sender ? response.data.sender.selectedDropOffPointId : null);
                        if (response.data.sender) {
                            response.data.sender.selectedDropOffPointId = defaultPointId;
                        }

                        var defaultDeliveryServiceId = response.defaultDeliveryServiceId || (response.data.shipmentSettings ? response.data.shipmentSettings.selectedDeliveryServiceId : null);
                        if (response.data.shipmentSettings) {
                            response.data.shipmentSettings.selectedDeliveryServiceId = defaultDeliveryServiceId;
                        }

                        var defaultCustomsTemplateId = response.defaultCustomsTemplateId;
                        if (response.data.customs) {
                            response.data.customs.selectedCustomsTemplateId = defaultCustomsTemplateId;
                        }

                        self._activeSenderTemplateId = null;
                        self._activeShipmentTemplateId = null;
                        self._activeCustomsTemplateId = null;
                        self._customsTemplateMatchRequestId = (self._customsTemplateMatchRequestId || 0) + 1;

                        // 3. Create reactive shipmentData instance AFTER options are set
                        var newShipmentData = createShipmentData(response.data, self.todayDate);
                        self.shipmentData(newShipmentData);

                        if (defaultPointId) {
                            newShipmentData.sender.selectedDropOffPointId(defaultPointId);
                        }
                        if (defaultDeliveryServiceId) {
                            newShipmentData.shipmentSettings.selectedDeliveryServiceId(defaultDeliveryServiceId);
                            self.onShipmentTemplateChange(defaultDeliveryServiceId);
                        }
                        if (defaultCustomsTemplateId) {
                            newShipmentData.customs.selectedCustomsTemplateId(defaultCustomsTemplateId);
                            self.onCustomsTemplateChange(defaultCustomsTemplateId);
                        }

                        newShipmentData.sender.selectedDropOffPointId.subscribe(function (newVal) {
                            self.onSenderTemplateChange(newVal);
                        });
                        newShipmentData.shipmentSettings.selectedDeliveryServiceId.subscribe(function (newVal) {
                            self.onShipmentTemplateChange(newVal);
                        });
                        newShipmentData.recipient.country.subscribe(function (newCountry) {
                            self.onRecipientCountryChange(newCountry);
                        });
                        newShipmentData.customs.selectedCustomsTemplateId.subscribe(function (newVal) {
                            self.onCustomsTemplateChange(newVal);
                        });

                        var modalOptions = {
                            type: 'slide',
                            title: $.mage.__('Create DHL Shipment'),
                            modalClass: 'dhl-prepare-shipment-modal',
                            buttons: [{
                                text: $.mage.__('Create DHL Shipment'),
                                class: 'action-primary',
                                click: function (e) {
                                    var selfModal = this;
                                    var $btn = $(e.target).closest('button');
                                    var $form = $('#ideo-dhl-prepare-modal-form');

                                    if ($form.length && typeof $form.valid === 'function' && !$form.valid()) {
                                        return false;
                                    }

                                    var shipmentDataPayload = ko.toJS(self.shipmentData());

                                    var hasCommaError = false;
                                    if (shipmentDataPayload) {
                                        var checkComma = function (val) {
                                            return val !== null && val !== undefined && String(val).indexOf(',') !== -1;
                                        };

                                        if (shipmentDataPayload.specialServices) {
                                            if (checkComma(shipmentDataPayload.specialServices.insuranceValue) || checkComma(shipmentDataPayload.specialServices.collectOnDeliveryValue)) {
                                                hasCommaError = true;
                                            }
                                        }
                                        if (shipmentDataPayload.shipmentSettings) {
                                            if (checkComma(shipmentDataPayload.shipmentSettings.returnLabelExpDays)) {
                                                hasCommaError = true;
                                            }
                                            if (Array.isArray(shipmentDataPayload.shipmentSettings.packages)) {
                                                $.each(shipmentDataPayload.shipmentSettings.packages, function (i, pkg) {
                                                    if (checkComma(pkg.weight) || checkComma(pkg.width) || checkComma(pkg.length) || checkComma(pkg.height) || checkComma(pkg.quantity)) {
                                                        hasCommaError = true;
                                                    }
                                                });
                                            }
                                        }
                                        if (shipmentDataPayload.customs) {
                                            if (checkComma(shipmentDataPayload.customs.costsOfShipment) || checkComma(shipmentDataPayload.customs.grossWeight)) {
                                                hasCommaError = true;
                                            }
                                            if (Array.isArray(shipmentDataPayload.customs.customsItems)) {
                                                $.each(shipmentDataPayload.customs.customsItems, function (i, item) {
                                                    if (checkComma(item.weight) || checkComma(item.value) || checkComma(item.quantity)) {
                                                        hasCommaError = true;
                                                    }
                                                });
                                            }
                                        }
                                    }

                                    if (hasCommaError) {
                                        alert({
                                            title: $.mage.__('Error'),
                                            content: $.mage.__('Commas are not allowed in numeric fields. Please use a dot (.) as decimal separator.')
                                        });
                                        return false;
                                    }

                                    var pFrom = shipmentDataPayload.sender ? shipmentDataPayload.sender.pickupHourFrom : null;
                                    var pTo = shipmentDataPayload.sender ? shipmentDataPayload.sender.pickupHourTo : null;
                                    var pkgs = (shipmentDataPayload.shipmentSettings && shipmentDataPayload.shipmentSettings.packages) ? shipmentDataPayload.shipmentSettings.packages : [];
                                    if (!pkgs.length) {
                                        alert({
                                            title: $.mage.__('Error'),
                                            content: $.mage.__('At least one package is required.')
                                        });
                                        return false;
                                    }

                                    if ((pFrom && !pTo) || (!pFrom && pTo)) {
                                        alert({
                                            title: $.mage.__('Error'),
                                            content: $.mage.__('Both Courier Pickup Time [From] and [To] must be specified.')
                                        });
                                        return false;
                                    }

                                    if (pFrom && pTo && pFrom >= pTo) {
                                        alert({
                                            title: $.mage.__('Error'),
                                            content: $.mage.__('Courier Pickup Time [From] must be earlier than Pickup Time [To].')
                                        });
                                        return false;
                                    }

                                    if (self.isCustomsClearanceVisible() && shipmentDataPayload.customs) {
                                        if (shipmentDataPayload.customs.customsType === 'I' && (!shipmentDataPayload.customs.invoiceFile || !shipmentDataPayload.customs.invoiceFile.trim())) {
                                            alert({
                                                title: $.mage.__('Error'),
                                                content: $.mage.__('VAT Invoice PDF file is required for individual customs clearance.')
                                            });
                                            return false;
                                        }
                                    }

                                    // Double-click protection & native full-page loader
                                    $btn.prop('disabled', true);
                                    if (typeof $('body').loader === 'function') {
                                        $('body').loader();
                                    }
                                    $('body').trigger('processStart');

                                    var senderTemplateId = (shipmentDataPayload.sender && shipmentDataPayload.sender.selectedDropOffPointId) ? shipmentDataPayload.sender.selectedDropOffPointId : null;
                                    var shipmentTemplateId = (shipmentDataPayload.shipmentSettings && shipmentDataPayload.shipmentSettings.selectedDeliveryServiceId) ? shipmentDataPayload.shipmentSettings.selectedDeliveryServiceId : null;
                                    var customsTemplateId = (shipmentDataPayload.customs && shipmentDataPayload.customs.selectedCustomsTemplateId) ? shipmentDataPayload.customs.selectedCustomsTemplateId : null;

                                    $.post(self.saveUrl, {
                                        shipment_data: JSON.stringify(shipmentDataPayload),
                                        drop_off_point_template_id: senderTemplateId,
                                        delivery_service_template_id: shipmentTemplateId,
                                        customs_template_id: customsTemplateId
                                    }).done(function (saveRes) {
                                        if (saveRes.success) {
                                            selfModal.closeModal();
                                            var successMsg = saveRes.message || ($.mage.__('DHL shipment %1 has been created successfully.').replace('%1', saveRes.shipmentId || ''));
                                            showAdminMessage('success', successMsg);
                                            // Keep full-page loader active until page reload finishes
                                            $('body').trigger('processStart');
                                            setTimeout(function () {
                                                window.location.reload();
                                            }, 300);
                                        } else {
                                            $('body').trigger('processStop');
                                            $btn.prop('disabled', false);
                                            alert({
                                                title: $.mage.__('Error'),
                                                content: createSafeMessageContent(saveRes.message || $.mage.__('Error creating DHL shipment.'))
                                            });
                                        }
                                    }).fail(function () {
                                        $('body').trigger('processStop');
                                        $btn.prop('disabled', false);
                                        alert({
                                            title: $.mage.__('Error'),
                                            content: $.mage.__('An error occurred while communicating with the server.')
                                        });
                                    });
                                }
                            }]
                        };
                        modal(modalOptions, $('#ideo-dhl-prepare-modal')).openModal();
                    } else {
                        alert({
                            title: $.mage.__('Error'),
                            content: createSafeMessageContent(response.message || $.mage.__('Error preparing DHL shipment.'))
                        });
                    }
                })
                .always(function () {
                    $('body').trigger('processStop');
                });
        }
    });
});
