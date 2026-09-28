define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'mage/validation',
    'mage/loader'
], function ($, ko, Component, modal, alert) {
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

    function formatDecimal4(val) {
        if (val === null || val === undefined || val === '') return '';
        var str = String(val).replace(',', '.');
        var num = parseFloat(str);
        return isNaN(num) ? '' : num.toFixed(4);
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

        pkgTypeObs.subscribe(function (newType) {
            if (newType !== 'PALLET') {
                euroReturnObs(false);
            }
        });

        var isNonStd = p.isNonStandard !== undefined ? p.isNonStandard : p.is_non_standard;
        var qtyVal = (p.quantity !== undefined && p.quantity !== null && p.quantity !== '') ? String(p.quantity).replace(',', '.') : '';

        return {
            packageType: pkgTypeObs,
            quantity: ko.observable(qtyVal),
            weight: ko.observable(formatDecimal4(p.weight)),
            width: ko.observable(formatDecimal4(p.width)),
            length: ko.observable(formatDecimal4(p.length)),
            height: ko.observable(formatDecimal4(p.height)),
            isNonStandard: ko.observable(isTruthy(isNonStd)),
            hasEuroReturn: euroReturnObs
        };
    }

    function createCustomsItemObservable(item) {
        item = item || {};
        var valStr = (item.value !== undefined && item.value !== null && item.value !== '') ? String(item.value).replace(',', '.') : '';
        var qtyStr = (item.quantity !== undefined && item.quantity !== null && item.quantity !== '') ? String(item.quantity).replace(',', '.') : 1;
        return {
            namePl: ko.observable(item.namePl || ''),
            nameEn: ko.observable(item.nameEn || ''),
            quantity: ko.observable(qtyStr),
            weight: ko.observable(formatDecimal4(item.weight)),
            value: ko.observable(valStr),
            tariffCode: ko.observable(item.tariffCode || ''),
            countryOfOrigin: ko.observable(item.countryOfOrigin || 'PL')
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
            customsItems: itemsObsArr
        };

        customsObj.addCustomsItemRow = function () {
            customsObj.customsItems.push(createCustomsItemObservable({
                namePl: '',
                nameEn: '',
                quantity: 1,
                weight: '',
                value: '',
                tariffCode: customsObj.defaultTariffCode() || '',
                countryOfOrigin: customsObj.countryOfOrigin() || 'PL'
            }));
        };

        customsObj.removeCustomsItemRow = function (index) {
            customsObj.customsItems.splice(index, 1);
        };

        return customsObj;
    }

    function createShipmentData(data) {
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
            postnummer: ko.observable(r.postnummer || ''),
            phone: ko.observable(r.phone || ''),
            email: ko.observable(r.email || '')
        };

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

        var todayStr = new Date().toISOString().split('T')[0];
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
            returnLabelExpDays: ko.observable(st.returnLabelExpDays !== undefined && st.returnLabelExpDays !== null ? st.returnLabelExpDays : 14),
            returnBookCourier: ko.observable(st.returnBookCourier !== undefined && st.returnBookCourier !== null ? st.returnBookCourier : 0),
            addressType: ko.observable(st.addressType || 'N'),
            packages: packagesObsArr
        };

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

        return {
            orderId: data.orderId || null,
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
                insurance: ko.observable(isTruthy(ss.insurance)),
                insuranceValue: ko.observable((ss.insuranceValue !== undefined && ss.insuranceValue !== null) ? ss.insuranceValue : ''),
                cod: ko.observable(isTruthy(ss.cod)),
                codAmount: ko.observable((ss.codAmount !== undefined && ss.codAmount !== null) ? ss.codAmount : ''),
                eveningDelivery: ko.observable(ss.deliveryHours === '1722' || isTruthy(ss.eveningDelivery)),
                saturdayDelivery: ko.observable(isTruthy(ss.saturdayDelivery)),
                saturdayDispatch: ko.observable(isTruthy(ss.saturdayDispatch || ss.pickupOnSaturday)),
                pdi: ko.observable(isTruthy(ss.pdi || ss.predeliveryInformation)),
                rod: ko.observable(isTruthy(ss.rod || ss.returnOnDelivery)),
                rodDocumentText: ko.observable((ss.rodDocumentText !== undefined && ss.rodDocumentText !== null) ? ss.rodDocumentText : ''),
                pod: ko.observable(isTruthy(ss.pod || ss.proofOfDelivery)),
                agever: ko.observable(isTruthy(ss.agever)),
                odb: ko.observable(isTruthy(ss.odb))
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
        var html = '<div class="message ' + cssClass + ' message-dismissible" data-ui-id="messages-message-' + type + '">' +
            '<div>' + messageText + '</div>' +
            '</div>';
        $messages.find('.messages').html(html);
        $('html, body').animate({ scrollTop: 0 }, 'fast');
    }

    return Component.extend({
        defaults: {
            saveUrl: '',
            loadTemplateUrl: '',
            loadPresetUrl: ''
        },

        initialize: function () {
            this._super();

            this.shipmentData = ko.observable(createShipmentData({}));
            this.draft = this.shipmentData; // Alias for backward compatibility
            this.senderTemplates = ko.observableArray([]);
            this.senderPresets = this.senderTemplates; // Alias
            this.countryOptions = ko.observableArray([]);
            this.shipmentTemplates = ko.observableArray([]);
            this.shipmentPresets = this.shipmentTemplates; // Alias
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

            var self = this;

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

        addPackageRow: function () {
            var settings = this.draft().shipmentSettings;
            if (settings && typeof settings.addPackageRow === 'function') {
                settings.addPackageRow();
            }
        },

        removePackageRow: function (index) {
            var settings = this.draft().shipmentSettings;
            if (settings && typeof settings.removePackageRow === 'function') {
                settings.removePackageRow(index);
            }
        },

        addCustomsItemRow: function () {
            var customs = this.draft() ? this.draft().customs : null;
            if (customs && typeof customs.addCustomsItemRow === 'function') {
                customs.addCustomsItemRow();
            }
        },

        removeCustomsItemRow: function (index) {
            var customs = this.draft() ? this.draft().customs : null;
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

            var maxSizeBytes = 10 * 1024 * 1024; // 10MB limit
            if (file.size > maxSizeBytes) {
                alert({
                    title: $.mage.__('Error'),
                    content: $.mage.__('File size exceeds the 10MB limit.')
                });
                event.target.value = '';
                return;
            }

            var self = this;
            var reader = new FileReader();
            reader.onload = function (e) {
                var result = e.target.result || '';
                var base64 = result.indexOf(',') !== -1 ? result.split(',')[1] : result;
                var customs = self.draft() ? self.draft().customs : null;
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
            var customs = this.draft() ? this.draft().customs : null;
            if (!customs) return;

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
            if (customs.customsItems) {
                customs.customsItems.removeAll();
            }
            this._activeCustomsTemplateId = null;
        },

        populateCustomsData: function (data) {
            var customs = this.draft() ? this.draft().customs : null;
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
            var customs = this.draft() ? this.draft().customs : null;
            if (!customs) return;

            if (event && event.target) {
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

            var url = this.loadTemplateUrl || this.loadPresetUrl;
            if (!url) return;

            var self = this;
            $.getJSON(url, {
                template_id: templateId,
                template_type: 'customs',
                preset_id: templateId,
                preset_type: 'customs',
                order_id: self.draft().orderId
            }).done(function (res) {
                if (res.success && res.data) {
                    self.populateCustomsData(res.data);
                }
            });
        },

        onSenderTemplateChange: function (templateId, event) {
            var sender = this.draft() ? this.draft().sender : null;
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
                if (this.draft() && this.draft().shipmentSettings) {
                    if (typeof this.draft().shipmentSettings.pickupHourFrom === 'function') {
                        this.draft().shipmentSettings.pickupHourFrom('');
                    }
                    if (typeof this.draft().shipmentSettings.pickupHourTo === 'function') {
                        this.draft().shipmentSettings.pickupHourTo('');
                    }
                }
                return;
            }

            var url = this.loadTemplateUrl || this.loadPresetUrl;
            if (!url) return;

            var self = this;
            $.getJSON(url, { template_id: templateId, template_type: 'sender', preset_id: templateId, preset_type: 'sender' })
                .done(function (res) {
                    if (res.success && res.data) {
                        var currentSender = self.draft().sender;
                        $.each(res.data, function (key, val) {
                            if (key === 'payer' || key === 'selectedDropOffPointId') return;
                            if (typeof currentSender[key] === 'function') {
                                if (key === 'country') {
                                    val = (val && val.length === 2) ? val.toUpperCase() : 'PL';
                                }
                                currentSender[key](val);
                            }
                        });
                        if (self.draft() && self.draft().shipmentSettings) {
                            if (res.data.pickupHourFrom !== undefined && typeof self.draft().shipmentSettings.pickupHourFrom === 'function') {
                                self.draft().shipmentSettings.pickupHourFrom(res.data.pickupHourFrom || '');
                            }
                            if (res.data.pickupHourTo !== undefined && typeof self.draft().shipmentSettings.pickupHourTo === 'function') {
                                self.draft().shipmentSettings.pickupHourTo(res.data.pickupHourTo || '');
                            }
                        }
                    }
                });
        },

        onSenderPresetChange: function () {
            return this.onSenderTemplateChange();
        },

        onShipmentTemplateChange: function (templateId, event) {
            var settings = this.draft() ? this.draft().shipmentSettings : null;
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
                if (this.draft().specialServices) {
                    var ssReset = this.draft().specialServices;
                    ssReset.insurance(false);
                    ssReset.insuranceValue('');
                    ssReset.cod(false);
                    ssReset.codAmount('');
                    ssReset.eveningDelivery(false);
                    ssReset.saturdayDelivery(false);
                    ssReset.saturdayDispatch(false);
                    ssReset.pdi(false);
                    ssReset.rod(false);
                    ssReset.rodDocumentText('');
                    ssReset.pod(false);
                    ssReset.agever(false);
                    ssReset.odb(false);
                }
                return;
            }

            var url = this.loadTemplateUrl || this.loadPresetUrl;
            if (!url) return;

            var self = this;
            $.getJSON(url, {
                template_id: templateId,
                template_type: 'shipment',
                preset_id: templateId,
                preset_type: 'shipment',
                order_id: self.draft().orderId
            }).done(function (res) {
                if (res.success && res.data) {
                    var shipmentSettings = res.data.shipmentSettings || res.data;
                    var specialServices = res.data.specialServices || res.specialServices;

                    if (shipmentSettings && self.draft().shipmentSettings) {
                        var currentSettings = self.draft().shipmentSettings;
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

                    if (specialServices && self.draft().specialServices) {
                        var currentSS = self.draft().specialServices;
                        if (specialServices.insuranceValue !== undefined && typeof currentSS.insuranceValue === 'function') {
                            currentSS.insuranceValue((specialServices.insuranceValue !== null && specialServices.insuranceValue !== undefined) ? specialServices.insuranceValue : '');
                        }
                        if (specialServices.codAmount !== undefined && typeof currentSS.codAmount === 'function') {
                            currentSS.codAmount((specialServices.codAmount !== null && specialServices.codAmount !== undefined) ? specialServices.codAmount : '');
                        }
                        if (specialServices.rodDocumentText !== undefined && typeof currentSS.rodDocumentText === 'function') {
                            currentSS.rodDocumentText(specialServices.rodDocumentText || '');
                        }
                        if (specialServices.proofOfDelivery !== undefined && typeof currentSS.pod === 'function') {
                            currentSS.pod(isTruthy(specialServices.proofOfDelivery));
                        }
                        if (specialServices.predeliveryInformation !== undefined && typeof currentSS.pdi === 'function') {
                            currentSS.pdi(isTruthy(specialServices.predeliveryInformation));
                        }
                        if (specialServices.returnOnDelivery !== undefined && typeof currentSS.rod === 'function') {
                            currentSS.rod(isTruthy(specialServices.returnOnDelivery));
                        }
                        if (specialServices.pickupOnSaturday !== undefined && typeof currentSS.saturdayDispatch === 'function') {
                            currentSS.saturdayDispatch(isTruthy(specialServices.pickupOnSaturday));
                        }
                        $.each(specialServices, function (key, val) {
                            if (key === 'insuranceValue' || key === 'codAmount' || key === 'rodDocumentText') return;
                            if (typeof currentSS[key] === 'function') {
                                currentSS[key](isTruthy(val));
                            }
                        });
                    }

                    // Dynamically update recipient.type based on template addressType
                    var addressType = (shipmentSettings && shipmentSettings.addressType) || (res.data && res.data.addressType) || 'N';
                    if (self.draft().recipient) {
                        if (addressType === 'B') {
                            self.draft().recipient.type('B');
                        } else if (addressType === 'C') {
                            self.draft().recipient.type('C');
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
                        var senderTemplates = response.senderTemplates || response.senderPresets || [];
                        var shipmentTemplates = response.shipmentTemplates || response.shipmentPresets || [];

                        self.senderTemplates(senderTemplates);
                        self.senderPresets(senderTemplates);
                        self.countryOptions(response.countryOptions || []);
                        self.shipmentTemplates(shipmentTemplates);
                        self.shipmentPresets(shipmentTemplates);
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

                        // 2. Set default IDs if missing
                        var defaultPointId = response.defaultDropOffPointId || (response.data.sender ? response.data.sender.selectedDropOffPointId : null);
                        if (response.data.sender) {
                            response.data.sender.selectedDropOffPointId = defaultPointId;
                        }

                        var defaultDeliveryServiceId = response.defaultDeliveryServiceId || (response.data.shipmentSettings ? response.data.shipmentSettings.selectedDeliveryServiceId : null);
                        if (response.data.shipmentSettings) {
                            response.data.shipmentSettings.selectedDeliveryServiceId = defaultDeliveryServiceId;
                        }

                        self._activeSenderTemplateId = null;
                        self._activeShipmentTemplateId = null;
                        self._activeCustomsTemplateId = null;

                        // 3. Create reactive shipmentData instance AFTER options are set
                        var newDraft = createShipmentData(response.data);
                        self.draft(newDraft);

                        if (defaultPointId) {
                            newDraft.sender.selectedDropOffPointId(defaultPointId);
                        }
                        if (defaultDeliveryServiceId) {
                            newDraft.shipmentSettings.selectedDeliveryServiceId(defaultDeliveryServiceId);
                            self.onShipmentTemplateChange(defaultDeliveryServiceId);
                        }

                        newDraft.sender.selectedDropOffPointId.subscribe(function (newVal) {
                            self.onSenderTemplateChange(newVal);
                        });
                        newDraft.shipmentSettings.selectedDeliveryServiceId.subscribe(function (newVal) {
                            self.onShipmentTemplateChange(newVal);
                        });
                        newDraft.recipient.country.subscribe(function (newCountry) {
                            if (newCountry && newCountry.toUpperCase() === 'PL') {
                                self.resetCustomsData();
                            }
                        });
                        newDraft.customs.selectedCustomsTemplateId.subscribe(function (newVal) {
                            self.onCustomsTemplateChange(newVal);
                        });

                        var modalOptions = {
                            type: 'slide',
                            title: $.mage.__('Create DHL Shipment'),
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

                                    var draftJs = ko.toJS(self.shipmentData());

                                    var hasCommaError = false;
                                    if (draftJs) {
                                        var checkComma = function (val) {
                                            return val !== null && val !== undefined && String(val).indexOf(',') !== -1;
                                        };

                                        if (draftJs.specialServices) {
                                            if (checkComma(draftJs.specialServices.insuranceValue) || checkComma(draftJs.specialServices.codAmount)) {
                                                hasCommaError = true;
                                            }
                                        }
                                        if (draftJs.shipmentSettings) {
                                            if (checkComma(draftJs.shipmentSettings.returnLabelExpDays)) {
                                                hasCommaError = true;
                                            }
                                            if (Array.isArray(draftJs.shipmentSettings.packages)) {
                                                $.each(draftJs.shipmentSettings.packages, function (i, pkg) {
                                                    if (checkComma(pkg.weight) || checkComma(pkg.width) || checkComma(pkg.length) || checkComma(pkg.height) || checkComma(pkg.quantity)) {
                                                        hasCommaError = true;
                                                    }
                                                });
                                            }
                                        }
                                        if (draftJs.customs) {
                                            if (checkComma(draftJs.customs.costsOfShipment) || checkComma(draftJs.customs.grossWeight)) {
                                                hasCommaError = true;
                                            }
                                            if (Array.isArray(draftJs.customs.customsItems)) {
                                                $.each(draftJs.customs.customsItems, function (i, item) {
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

                                    var pFrom = draftJs.sender ? draftJs.sender.pickupHourFrom : null;
                                    var pTo = draftJs.sender ? draftJs.sender.pickupHourTo : null;
                                    var pkgs = (draftJs.shipmentSettings && draftJs.shipmentSettings.packages) ? draftJs.shipmentSettings.packages : [];
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

                                    if (self.isCustomsClearanceVisible() && draftJs.customs) {
                                        var customsItems = draftJs.customs.customsItems || [];
                                        if (!customsItems.length) {
                                            alert({
                                                title: $.mage.__('Error'),
                                                content: $.mage.__('At least one customs goods item is required when customs clearance is selected.')
                                            });
                                            return false;
                                        }

                                        if (draftJs.customs.customsType === 'I' && (!draftJs.customs.invoiceFile || !draftJs.customs.invoiceFile.trim())) {
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

                                    var senderTemplateId = (draftJs.sender && draftJs.sender.selectedDropOffPointId) ? draftJs.sender.selectedDropOffPointId : null;
                                    var shipmentTemplateId = (draftJs.shipmentSettings && draftJs.shipmentSettings.selectedDeliveryServiceId) ? draftJs.shipmentSettings.selectedDeliveryServiceId : null;
                                    var customsTemplateId = (draftJs.customs && draftJs.customs.selectedCustomsTemplateId) ? draftJs.customs.selectedCustomsTemplateId : null;

                                    $.post(self.saveUrl, {
                                        payload: JSON.stringify(draftJs),
                                        shipment_data: JSON.stringify(draftJs),
                                        draft: JSON.stringify(draftJs),
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
                                            var errorMsg = (saveRes.message || $.mage.__('Error creating DHL shipment.'))
                                                .replace(/\n/g, '<br/>');
                                            alert({
                                                title: $.mage.__('Error'),
                                                content: errorMsg
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
                            content: response.message || $.mage.__('Error preparing DHL shipment.')
                        });
                    }
                })
                .always(function () {
                    $('body').trigger('processStop');
                });
        }
    });
});
