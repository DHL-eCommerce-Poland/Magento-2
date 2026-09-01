define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'mage/validation'
], function ($, ko, Component, modal, alert) {
    'use strict';

    function formatDecimal4(val) {
        if (val === null || val === undefined || val === '') return '';
        var num = parseFloat(val);
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
        return {
            packageType: ko.observable(p.packageType || 'PACKAGE'),
            quantity: ko.observable(p.quantity !== undefined ? p.quantity : 1),
            weight: ko.observable(formatDecimal4(p.weight !== undefined ? p.weight : 1.0)),
            width: ko.observable(formatDecimal4(p.width)),
            length: ko.observable(formatDecimal4(p.length)),
            height: ko.observable(formatDecimal4(p.height)),
            isNonStandard: ko.observable(isTruthy(p.isNonStandard)),
            hasEuroReturn: ko.observable(isTruthy(p.hasEuroReturn))
        };
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

        var recipientObj = {
            type: ko.observable(r.type || 'C'),
            name: ko.observable(r.name || ''),
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

        var packagesRaw = st.packages || [];
        var packagesObsArr = ko.observableArray([]);

        if (Array.isArray(packagesRaw) && packagesRaw.length > 0) {
            $.each(packagesRaw, function (i, pData) {
                packagesObsArr.push(createPackageObservable(pData));
            });
        } else {
            packagesObsArr.push(createPackageObservable({
                packageType: st.packageType || 'PACKAGE',
                quantity: st.packageQuantity || 1,
                weight: st.weight,
                width: st.width,
                height: st.height,
                length: st.length,
                isNonStandard: st.isNonStandard,
                hasEuroReturn: st.hasEuroReturn
            }));
        }

        var todayStr = new Date().toISOString().split('T')[0];
        var dDate = st.shipmentDate || st.dispatchDate || todayStr;
        if (dDate < todayStr) {
            dDate = todayStr;
        }

        var shipmentSettingsObj = {
            selectedDeliveryServiceId: ko.observable(defaultServiceId),
            serviceType: ko.observable(st.serviceType || ''),
            shipmentDate: ko.observable(dDate),
            dispatchDate: ko.observable(dDate),
            content: ko.observable(st.content !== undefined ? st.content : ''),
            mpk: ko.observable(st.mpk || ''),
            comment: ko.observable(st.comment || ''),
            reference: ko.observable(st.reference || ''),
            pickupHourFrom: pickupHourFromObs,
            pickupHourTo: pickupHourToObs,
            labelType: ko.observable(st.labelType || 'BLP'),
            autoCreateReturnShipment: ko.observable(!!st.autoCreateReturnShipment),
            addressType: ko.observable(st.addressType || 'N'),
            packages: packagesObsArr
        };

        shipmentSettingsObj.isSinglePieceOnly = ko.computed(function () {
            var sType = (shipmentSettingsObj.serviceType() || '').toUpperCase().trim();
            var popIdVal = (recipientObj.popId() || '').trim();
            if (popIdVal !== '') return true;
            return sType === 'SP' || sType === 'PS' || sType === 'LM';
        });

        shipmentSettingsObj.isNonStandardForbidden = ko.computed(function () {
            var sType = (shipmentSettingsObj.serviceType() || '').toUpperCase().trim();
            var popIdVal = (recipientObj.popId() || '').trim();
            if (popIdVal !== '') return true;
            return sType === 'SP' || sType === 'PS' || sType === 'LM';
        });

        var checkAndEnforceRestrictions = function () {
            if (shipmentSettingsObj.isSinglePieceOnly()) {
                if (shipmentSettingsObj.packages().length > 1) {
                    shipmentSettingsObj.packages.splice(1, shipmentSettingsObj.packages().length - 1);
                }
                if (shipmentSettingsObj.packages().length > 0) {
                    shipmentSettingsObj.packages()[0].quantity(1);
                }
            }
            if (shipmentSettingsObj.isNonStandardForbidden()) {
                if (shipmentSettingsObj.packages().length > 0) {
                    $.each(shipmentSettingsObj.packages(), function (i, pkg) {
                        pkg.isNonStandard(false);
                    });
                }
            }
        };

        shipmentSettingsObj.serviceType.subscribe(checkAndEnforceRestrictions);
        recipientObj.popId.subscribe(checkAndEnforceRestrictions);

        shipmentSettingsObj.addPackageRow = function () {
            if (shipmentSettingsObj.isSinglePieceOnly()) return;
            shipmentSettingsObj.packages.push(createPackageObservable({
                packageType: 'PACKAGE',
                quantity: 1,
                weight: '1.0000',
                width: '',
                length: '',
                height: '',
                isNonStandard: false,
                hasEuroReturn: false
            }));
        };

        shipmentSettingsObj.removePackageRow = function (index) {
            if (shipmentSettingsObj.packages().length > 1) {
                shipmentSettingsObj.packages.splice(index, 1);
            }
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
                insurance: ko.observable(isTruthy(ss.insurance || ss.service_UBEZP)),
                insuranceValue: ko.observable(ss.insuranceValue !== undefined ? ss.insuranceValue : (ss.serviceValueUbez !== undefined ? ss.serviceValueUbez : '')),
                cod: ko.observable(isTruthy(ss.cod || ss.service_COD)),
                codAmount: ko.observable(ss.codAmount !== undefined ? ss.codAmount : (ss.serviceValueCOD !== undefined ? ss.serviceValueCOD : '')),
                eveningDelivery: ko.observable(ss.deliveryHours === '1722' || isTruthy(ss.eveningDelivery || ss.service_1722)),
                saturdayDelivery: ko.observable(isTruthy(ss.saturdayDelivery || ss.service_SOBOTA)),
                saturdayDispatch: ko.observable(isTruthy(ss.saturdayDispatch || ss.pickupOnSaturday || ss.service_NAD_SOBOTA)),
                pdi: ko.observable(isTruthy(ss.pdi || ss.predeliveryInformation || ss.service_PDI)),
                rod: ko.observable(isTruthy(ss.rod || ss.returnOnDelivery || ss.service_ROD)),
                rodDocumentText: ko.observable(ss.rodDocumentText !== undefined ? ss.rodDocumentText : (ss.textInstruction !== undefined ? ss.textInstruction : '')),
                pod: ko.observable(isTruthy(ss.pod || ss.proofOfDelivery || ss.service_POD)),
                agever: ko.observable(isTruthy(ss.agever || ss.service_AGEVER)),
                odb: ko.observable(isTruthy(ss.odb || ss.service_ODB))
            },
            shipmentSettings: shipmentSettingsObj
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
            this.contentSourceOptions = ko.observableArray([]);

            var self = this;
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

        onSenderTemplateChange: function () {
            var sender = this.draft().sender;
            var templateId = sender.selectedDropOffPointId();

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

        onShipmentTemplateChange: function () {
            var settings = this.draft().shipmentSettings;
            var templateId = settings.selectedDeliveryServiceId();

            if (!templateId) {
                settings.serviceType('');
                settings.content('');
                settings.mpk('');
                settings.comment('');
                settings.reference('');
                settings.labelType('BLP');
                settings.autoCreateReturnShipment(false);
                settings.addressType('N');
                if (settings.packages && settings.packages().length > 0) {
                    var p0 = settings.packages()[0];
                    p0.packageType('PACKAGE');
                    p0.weight('1.0000');
                    p0.width('');
                    p0.height('');
                    p0.length('');
                    p0.isNonStandard(false);
                    p0.hasEuroReturn(false);
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
                            if (key === 'selectedDeliveryServiceId' || key === 'packages' || key === 'isSinglePieceOnly' || key === 'isNonStandardForbidden') return;
                            if (typeof currentSettings[key] === 'function') {
                                if (typeof val === 'string') {
                                    val = val.trim();
                                }
                                currentSettings[key](val);
                            }
                        });

                        // Update first package row with template defaults
                        if (currentSettings.packages && currentSettings.packages().length > 0) {
                            var p0 = currentSettings.packages()[0];
                            if (shipmentSettings.packageType) p0.packageType(shipmentSettings.packageType);
                            if (shipmentSettings.weight !== undefined) p0.weight(formatDecimal4(shipmentSettings.weight));
                            if (shipmentSettings.width !== undefined) p0.width(formatDecimal4(shipmentSettings.width));
                            if (shipmentSettings.height !== undefined) p0.height(formatDecimal4(shipmentSettings.height));
                            if (shipmentSettings.length !== undefined) p0.length(formatDecimal4(shipmentSettings.length));
                            if (shipmentSettings.isNonStandard !== undefined) p0.isNonStandard(!!shipmentSettings.isNonStandard);
                            if (shipmentSettings.hasEuroReturn !== undefined) p0.hasEuroReturn(!!shipmentSettings.hasEuroReturn);
                        }
                    }

                    if (specialServices && self.draft().specialServices) {
                        var currentSS = self.draft().specialServices;
                        if (specialServices.insuranceValue !== undefined && typeof currentSS.insuranceValue === 'function') {
                            currentSS.insuranceValue(specialServices.insuranceValue);
                        } else if (specialServices.serviceValueUbez !== undefined && typeof currentSS.insuranceValue === 'function') {
                            currentSS.insuranceValue(specialServices.serviceValueUbez);
                        }
                        if (specialServices.codAmount !== undefined && typeof currentSS.codAmount === 'function') {
                            currentSS.codAmount(specialServices.codAmount);
                        } else if (specialServices.serviceValueCOD !== undefined && typeof currentSS.codAmount === 'function') {
                            currentSS.codAmount(specialServices.serviceValueCOD);
                        }
                        if (specialServices.rodDocumentText !== undefined && typeof currentSS.rodDocumentText === 'function') {
                            currentSS.rodDocumentText(specialServices.rodDocumentText);
                        } else if (specialServices.textInstruction !== undefined && typeof currentSS.rodDocumentText === 'function') {
                            currentSS.rodDocumentText(specialServices.textInstruction);
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
                        } else {
                            var companyName = self.draft().recipient.name();
                            var hasCompany = !!(companyName && String(companyName).trim());
                            self.draft().recipient.type(hasCompany ? 'B' : 'C');
                        }
                    }

                    var dropOffPointId = (shipmentSettings && shipmentSettings.dropOffPointId) || res.data.dropOffPointId;
                    if (dropOffPointId && self.draft().sender) {
                        self.draft().sender.selectedDropOffPointId(dropOffPointId);
                        self.onSenderTemplateChange();
                    }
                }
            });
        },

        onShipmentPresetChange: function () {
            return this.onShipmentTemplateChange();
        },

        openModal: function (orderId, prepareUrl) {
            var self = this;
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
                        self.contentSourceOptions(response.contentSourceOptions || []);

                        // 2. Set default IDs if missing
                        var defaultPointId = response.defaultDropOffPointId || (response.data.sender ? response.data.sender.selectedDropOffPointId : null);
                        if (response.data.sender) {
                            response.data.sender.selectedDropOffPointId = defaultPointId;
                        }

                        var defaultDeliveryServiceId = response.defaultDeliveryServiceId || (response.data.shipmentSettings ? response.data.shipmentSettings.selectedDeliveryServiceId : null);
                        if (response.data.shipmentSettings) {
                            response.data.shipmentSettings.selectedDeliveryServiceId = defaultDeliveryServiceId;
                        }

                        // 3. Create reactive shipmentData instance AFTER options are set
                        var newDraft = createShipmentData(response.data);
                        self.draft(newDraft);

                        if (defaultPointId) {
                            newDraft.sender.selectedDropOffPointId(defaultPointId);
                        }
                        if (defaultDeliveryServiceId) {
                            newDraft.shipmentSettings.selectedDeliveryServiceId(defaultDeliveryServiceId);
                            self.onShipmentTemplateChange();
                        }

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
                                    
                                    var draftJs = ko.toJS(self.shipmentData());
                                    var pFrom = draftJs.sender ? draftJs.sender.pickupHourFrom : null;
                                    var pTo = draftJs.sender ? draftJs.sender.pickupHourTo : null;

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

                                    // Double-click protection & native loader
                                    $btn.prop('disabled', true);
                                    $('body').trigger('processStart');

                                    var draftJs = ko.toJS(self.draft());
                                    var senderPresetId = (draftJs.sender && draftJs.sender.selectedDropOffPointId) ? draftJs.sender.selectedDropOffPointId : null;
                                    var shipmentPresetId = (draftJs.shipmentSettings && draftJs.shipmentSettings.selectedDeliveryServiceId) ? draftJs.shipmentSettings.selectedDeliveryServiceId : null;

                                    $.post(self.saveUrl, {
                                        shipment_data: JSON.stringify(draftJs),
                                        draft: JSON.stringify(draftJs),
                                        preset_sender_id: senderPresetId,
                                        preset_shipment_id: shipmentPresetId
                                    }).done(function (saveRes) {
                                        if (saveRes.success) {
                                            selfModal.closeModal();
                                            var successMsg = saveRes.message || ($.mage.__('DHL shipment %1 has been created successfully.').replace('%1', saveRes.shipmentId || ''));
                                            showAdminMessage('success', successMsg);
                                            setTimeout(function () {
                                                window.location.reload();
                                            }, 800);
                                        } else {
                                            var errorMsg = (saveRes.message || $.mage.__('Error creating DHL shipment.'))
                                                .replace(/\n/g, '<br/>');
                                            alert({
                                                title: $.mage.__('Error'),
                                                content: errorMsg
                                            });
                                        }
                                    }).always(function () {
                                        $('body').trigger('processStop');
                                        $btn.prop('disabled', false);
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
