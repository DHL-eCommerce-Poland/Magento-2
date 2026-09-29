define([
    'jquery',
    'Magento_Ui/js/form/components/button',
    'uiRegistry',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, Button, registry, modal, $t) {
    'use strict';

    return Button.extend({
        defaults: {
            provider: 'dhlparcel_shipment_form.dhlparcel_shipment_form_data_source',
            template: 'ui/form/components/button/container',
            elementTmpl: 'Ideo_Dhl/form/element/map-point-button',
            buttonClasses: 'action-secondary',
            additionalClasses: 'dhl-map-point-button-field',
            title: $t('Select delivery point from map'),
            label: $t('Delivery Point'),
            labelVisible: true,
            imports: {
                updateServiceType: '${ $.provider }:data.shipmentSettings.serviceType',
                updateMapPointType: '${ $.provider }:data.recipient.mapPointType',
                updateCountry: '${ $.provider }:data.recipient.country'
            }
        },

        /**
         * Initialize button component and attach window message listener for map point selection.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            this.serviceType = '';
            this.mapPointType = '';
            this.country = '';

            var self = this;
            var providerName = this.provider || 'dhlparcel_shipment_form.dhlparcel_shipment_form_data_source';

            registry.get(providerName, function (source) {
                self.source = source;

                var updateFromSource = function () {
                    var st = source.get('data.shipmentSettings.serviceType');
                    var pt = source.get('data.recipient.mapPointType');
                    var c = source.get('data.recipient.country');

                    if (st !== undefined && st !== null && st !== '') {
                        self.serviceType = st;
                    }
                    if (pt !== undefined && pt !== null && pt !== '') {
                        self.mapPointType = pt;
                    }
                    if (c !== undefined && c !== null && c !== '') {
                        self.country = c;
                    }
                    self.refreshButtonState();
                };

                updateFromSource();

                if (typeof source.on === 'function') {
                    source.on('data', updateFromSource);
                    source.on('data.shipmentSettings', updateFromSource);
                    source.on('data.shipmentSettings.serviceType', function (val) {
                        self.updateServiceType(val);
                    });
                    source.on('data.recipient.mapPointType', function (val) {
                        self.updateMapPointType(val);
                    });
                    source.on('data.recipient.country', function (val) {
                        self.updateCountry(val);
                    });
                }
            });

            this.initMessageListener();
            return this;
        },

        /**
         * Update shipment service type.
         *
         * @param {String} serviceType
         */
        updateServiceType: function (serviceType) {
            this.serviceType = serviceType || '';
            this.refreshButtonState();
        },

        /**
         * Update map point type.
         *
         * @param {String} mapPointType
         */
        updateMapPointType: function (mapPointType) {
            this.mapPointType = mapPointType || '';
            this.refreshButtonState();
        },

        /**
         * Update recipient country.
         *
         * @param {String} country
         */
        updateCountry: function (country) {
            this.country = country || '';
            this.refreshButtonState();
        },

        /**
         * Refresh element visibility and disabled state.
         * Visible when service type is 'SP', 'PS' or 'EK'.
         * Disabled ONLY when recipient country is non-PL (international) and map point type is 'courier'.
         */
        refreshButtonState: function () {
            var st = this.serviceType ? String(this.serviceType).toUpperCase() : '';
            var isPointService = st === 'SP' || st === 'EK';
            var countryUpper = this.country ? String(this.country).toUpperCase() : '';
            var isNonPl = countryUpper !== '' && countryUpper !== 'PL';
            var isCourierPointType = this.mapPointType === 'courier';

            this.visible(isPointService);
            this.disabled(isNonPl && isCourierPointType);
        },

        /**
         * Attach postMessage event listener to update form provider data on point selection.
         */
        initMessageListener: function () {
            var self = this;
            if (this._messageListener) {
                return;
            }

            this._messageListener = function (event) {
                if (!event || !event.data) {
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
                    var popId = pointData.sap || '';
                    var nameStr = pointData.name || '';
                    var addrStr = pointData.address || '';
                    if (!addrStr && (pointData.street || pointData.city)) {
                        var streetStr = $.trim((pointData.street || '') + ' ' + (pointData.houseNo || pointData.streetNo || ''));
                        var cityStr = $.trim((pointData.zip || pointData.postalCode || '') + ' ' + (pointData.city || ''));
                        addrStr = $.trim(streetStr + (streetStr && cityStr ? ', ' : '') + cityStr);
                    }
                    var popName = (nameStr && addrStr && nameStr !== addrStr) ? (nameStr + ' - ' + addrStr) : (nameStr || addrStr || pointData.sap || '');
                    var postnummer = pointData.postnummer || '';
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
                    var popPostcode = pointData.zip || pointData.postalCode || '';
                    var popCity = pointData.city || '';

                    if (self.source) {
                        self.source.set('data.recipient.popId', popId);
                        self.source.set('data.recipient.popName', popName);
                        self.source.set('data.recipient.popStreet', popStreet);
                        self.source.set('data.recipient.popPostcode', popPostcode);
                        self.source.set('data.recipient.popCity', popCity);
                        if (postnummer) {
                            self.source.set('data.recipient.postnummer', postnummer);
                        }
                    }

                    var targets = [
                        { query: 'dataScope = recipient.popId', val: popId },
                        { query: 'name = recipient[popId]', val: popId },
                        { query: 'dataScope = recipient.popName', val: popName },
                        { query: 'name = recipient[popName]', val: popName },
                        { query: 'dataScope = recipient.popStreet', val: popStreet },
                        { query: 'name = recipient[popStreet]', val: popStreet },
                        { query: 'dataScope = recipient.popPostcode', val: popPostcode },
                        { query: 'name = recipient[popPostcode]', val: popPostcode },
                        { query: 'dataScope = recipient.popCity', val: popCity },
                        { query: 'name = recipient[popCity]', val: popCity }
                    ];
                    if (postnummer) {
                        targets.push({ query: 'dataScope = recipient.postnummer', val: postnummer });
                        targets.push({ query: 'name = recipient[postnummer]', val: postnummer });
                    }

                    targets.forEach(function (item) {
                        registry.get(item.query, function (comp) {
                            if (comp && typeof comp.value === 'function') {
                                comp.value(item.val);
                            }
                        });
                    });

                    if (self._activeModal) {
                        self._activeModal.closeModal();
                    }
                }
            };

            if (window.addEventListener) {
                window.addEventListener('message', this._messageListener, false);
            } else if (window.attachEvent) {
                window.attachEvent('onmessage', this._messageListener);
            }
        },

        /**
         * Open DHL point selection map in a popup modal.
         */
        action: function () {
            if (this.disabled()) {
                return;
            }

            var self = this;
            var country = (this.source ? this.source.get('data.recipient.country') : 'PL') || 'PL';
            country = String(country).toUpperCase();

            var baseUrl = 'https://parcelshop.dhl.pl/mapa';
            var params = [];

            if (country !== 'PL') {
                params.push('country=' + encodeURIComponent(country));
                var pType = this.source ? this.source.get('data.recipient.mapPointType') : 'parcelShop';
                pType = pType || 'parcelShop';
                params.push('ptype=' + encodeURIComponent(pType));
            }

            var iframeUrl = params.length ? baseUrl + '?' + params.join('&') : baseUrl;

            var $modalContainer = $('#ideo-dhl-admin-form-map-modal');
            if (!$modalContainer.length) {
                $modalContainer = $('<div id="ideo-dhl-admin-form-map-modal" style="display:none;"><iframe id="ideo-dhl-admin-form-map-iframe" style="width:100%; height:80vh; min-height:500px; border:none;"></iframe></div>');
                $('body').append($modalContainer);
            }

            $modalContainer.find('iframe').attr('src', iframeUrl);

            var options = {
                type: 'popup',
                responsive: true,
                innerScroll: false,
                title: $t('Select DHL Delivery Point'),
                modalClass: 'dhl-map-modal-popup',
                buttons: [],
                closed: function () {
                    $modalContainer.find('iframe').attr('src', '');
                }
            };

            if (!$modalContainer.data('mageModal')) {
                modal(options, $modalContainer);
            }
            $modalContainer.modal('openModal');
            self._activeModal = $modalContainer.data('mageModal');
        }
    });
});
