define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, modal, $t) {
    'use strict';

    return function (config, element) {
        var activeModal = null;

        var decodeEntities = function (str) {
            if (!str) return '';
            var txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        };

        var updateDisplayText = function (id, name, street, postcode, city) {
            if (!id) {
                $('.dhl-sp-display-text').text($t('None'));
                return;
            }
            var nameDecoded = decodeEntities(name);
            var streetDecoded = decodeEntities(street);
            var postcodeDecoded = decodeEntities(postcode);
            var cityDecoded = decodeEntities(city);

            var parts = [];
            if (nameDecoded) {
                parts.push(nameDecoded);
            }
            if (streetDecoded && streetDecoded !== nameDecoded) {
                parts.push(streetDecoded);
            }
            var pcCity = $.trim((postcodeDecoded || '') + ' ' + (cityDecoded || ''));
            if (pcCity && nameDecoded.indexOf(pcCity) === -1 && streetDecoded.indexOf(pcCity) === -1) {
                parts.push(pcCity);
            }

            var uniqueParts = [];
            $.each(parts, function (i, el) {
                if ($.inArray(el, uniqueParts) === -1) {
                    uniqueParts.push(el);
                }
            });

            var txt = '(' + id + ') ' + uniqueParts.join(', ');
            $('.dhl-sp-display-text').text(txt);
        };

        var updateDisplayTextFromHiddenInputs = function () {
            var id = $('#dhl_service_point_id').val() || '';
            var name = $('#dhl_service_point_name').val() || '';
            var street = $('#dhl_service_point_street').val() || '';
            var postcode = $('#dhl_service_point_postcode').val() || '';
            var city = $('#dhl_service_point_city').val() || '';
            updateDisplayText(id, name, street, postcode, city);
        };

        var getShippingAddressCountry = function () {
            var formCountry = $('#order-shipping_address_country_id').val() || $('select[name="order[shipping_address][country_id]"]').val();
            if (formCountry) {
                return String(formCountry).toUpperCase();
            }
            return (config && config.country) ? String(config.country).toUpperCase() : 'PL';
        };

        var getSelectedMethodCode = function () {
            var $checked = $('input[name="order[shipping_method]"]:checked');
            if ($checked.length) {
                return $checked.val() || '';
            }
            return '';
        };

        var isDhlSpMethod = function (methodCode) {
            if (!methodCode) return false;
            var cleanMethod = methodCode.indexOf('dhlpoland_') === 0 ? methodCode.substring(10) : methodCode;
            var allowedMethods = [
                'economy_all',
                'economy_all_cod',
                'economy_pop',
                'economy_pop_cod',
                'economy_box',
                'economy_box_cod',
                'connect_parcelshop',
                'connect_parcelshop_cod',
                'connect_packstation',
                'connect_packstation_cod'
            ];
            if ($.inArray(cleanMethod, allowedMethods) !== -1) {
                return true;
            }
            return /^(economy_|connect_parcelshop|connect_packstation)/i.test(cleanMethod);
        };

        var isCodMethod = function (methodCode) {
            if (!methodCode) return false;
            var lower = methodCode.toLowerCase();
            return lower.indexOf('_cod') !== -1 || lower.endsWith('cod');
        };

        var decorateDhlMethods = function () {
            var $radios = $('input[name="order[shipping_method]"]');
            $radios.each(function () {
                var $radio = $(this);
                var val = $radio.val() || '';
                if (isDhlSpMethod(val)) {
                    var $li = $radio.closest('li');
                    if ($li.length && !$li.find('.dhl-service-point-selector-wrapper').length) {
                        var isChecked = $radio.is(':checked');
                        var html = '<div class="dhl-service-point-selector-wrapper" style="' + (isChecked ? '' : 'display: none;') + '">' +
                            '<span class="dhl-sp-label">' + $t('Selected DHL pickup point:') + '</span> ' +
                            '<strong class="dhl-sp-display-text">' + $t('None') + '</strong>' +
                            '&nbsp;' +
                            '<a href="#" class="action-secondary dhl-show-map-btn">' +
                                '<span>' + $t('Select DHL pickup point') + '</span>' +
                            '</a>' +
                            '<div class="dhl-postnummer-wrapper" style="display: none;">' +
                                '<label for="dhl_postnummer_input" class="admin__field-label dhl-postnummer-label">' +
                                    $t('German Customer Number (Postnummer):') +
                                '</label>' +
                                '<input type="text" id="dhl_postnummer_input" class="admin__control-text dhl-postnummer-input" value="' + (decodeEntities($('#dhl_postnummer').val()) || '') + '" placeholder="' + $t('6-10 digits') + '" />' +
                            '</div>' +
                        '</div>';
                        var $label = $li.find('label[for="' + $radio.attr('id') + '"]');
                        if ($label.length) {
                            $label.after(html);
                        } else {
                            $radio.after(html);
                        }
                    }
                }
            });

            var $infoOptions = $('#order-shipping-method-info dd.admin__order-shipment-methods-options');
            if ($infoOptions.length && !$infoOptions.find('.dhl-service-point-selector-wrapper').length) {
                var checkedVal = getSelectedMethodCode();
                if (isDhlSpMethod(checkedVal)) {
                    var htmlInfo = '<div class="dhl-service-point-selector-wrapper dhl-sp-active-info">' +
                        '<span class="dhl-sp-label">' + $t('Selected DHL pickup point:') + '</span> ' +
                        '<strong class="dhl-sp-display-text">' + $t('None') + '</strong>' +
                        '&nbsp;' +
                        '<a href="#" class="action-secondary dhl-show-map-btn">' +
                            '<span>' + $t('Select DHL pickup point') + '</span>' +
                        '</a>' +
                        '<div class="dhl-postnummer-wrapper" style="display: none;">' +
                            '<label for="dhl_postnummer_input" class="admin__field-label dhl-postnummer-label">' +
                                $t('German Customer Number (Postnummer):') +
                            '</label>' +
                            '<input type="text" id="dhl_postnummer_input" class="admin__control-text dhl-postnummer-input" value="' + (decodeEntities($('#dhl_postnummer').val()) || '') + '" placeholder="' + $t('6-10 digits') + '" />' +
                        '</div>' +
                    '</div>';
                    $infoOptions.append(htmlInfo);
                }
            }
        };

        var updatePointSelectorVisibility = function () {
            $('#order-shipping-method-choose .dhl-service-point-selector-wrapper').hide();
            var $checked = $('input[name="order[shipping_method]"]:checked');
            if ($checked.length) {
                $checked.closest('li').find('.dhl-service-point-selector-wrapper').show();
            }
        };

        var togglePostnummerVisibility = function () {
            var country = getShippingAddressCountry();
            var selectedMethod = getSelectedMethodCode();
            var cleanMethod = selectedMethod.indexOf('dhlpoland_') === 0 ? selectedMethod.substring(10) : selectedMethod;
            var isPackstation = cleanMethod === 'connect_packstation' || cleanMethod === 'connect_packstation_cod' || $('#dhl_is_packstation').val() === '1';

            if (country === 'DE' && isPackstation) {
                $('.dhl-postnummer-wrapper').show();
            } else {
                $('.dhl-postnummer-wrapper').hide();
            }
        };

        var initMessageListener = function () {
            window.addEventListener('message', function (event) {
                var iframe = document.getElementById('ideo-dhl-admin-order-create-map-iframe');
                if (!event || event.origin !== 'https://parcelshop.dhl.pl' || !iframe
                    || event.source !== iframe.contentWindow || !activeModal || !event.data) {
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
                    var popId = decodeEntities(pointData.sap || '');
                    var rawName = decodeEntities(pointData.name || '');
                    var rawStreet = decodeEntities(pointData.street || '');
                    var rawHouseNo = decodeEntities(pointData.houseNo || pointData.streetNo || '');
                    var popPostcode = decodeEntities(pointData.zip || pointData.postalCode || '');
                    var popCity = decodeEntities(pointData.city || '');
                    var postnummer = decodeEntities(pointData.postnummer || '');

                    var streetWithHouse = rawStreet;
                    if (rawHouseNo && rawStreet && rawStreet.indexOf(rawHouseNo) === -1) {
                        streetWithHouse = rawStreet + ' ' + rawHouseNo;
                    } else if (!rawStreet && rawHouseNo) {
                        streetWithHouse = rawHouseNo;
                    }

                    var popName = rawName || streetWithHouse || popId;
                    var popStreet = streetWithHouse || rawName || popId;

                    var typeStr = (pointData.type || pointData.ptype || '').toLowerCase();
                    var selectedMethod = getSelectedMethodCode();
                    var cleanMethod = selectedMethod.indexOf('dhlpoland_') === 0 ? selectedMethod.substring(10) : selectedMethod;

                    var isPackstation = cleanMethod === 'connect_packstation' || cleanMethod === 'connect_packstation_cod' || cleanMethod === 'economy_box' || cleanMethod === 'economy_box_cod' || typeStr === 'packstation';
                    var isPostfiliale = cleanMethod === 'connect_parcelshop' || cleanMethod === 'connect_parcelshop_cod' || cleanMethod === 'economy_pop' || cleanMethod === 'economy_pop_cod' || typeStr === 'parcelshop' || typeStr === 'pop';

                    $('#dhl_service_point_id').val(popId);
                    $('#dhl_service_point_name').val(popName);
                    $('#dhl_service_point_street').val(popStreet);
                    $('#dhl_service_point_postcode').val(popPostcode);
                    $('#dhl_service_point_city').val(popCity);
                    $('#dhl_is_packstation').val(isPackstation ? 1 : 0);
                    $('#dhl_is_postfiliale').val(isPostfiliale ? 1 : 0);
                    if (postnummer) {
                        $('#dhl_postnummer').val(postnummer);
                        $('#dhl_postnummer_input').val(postnummer);
                    }

                    updateDisplayText(popId, popName, popStreet, popPostcode, popCity);
                    togglePostnummerVisibility();

                    if (activeModal) {
                        activeModal.closeModal();
                    }
                }
            }, false);
        };

        $(element).on('click', '.dhl-show-map-btn', function (e) {
            e.preventDefault();

            var country = getShippingAddressCountry();
            var selectedMethod = getSelectedMethodCode();
            var cleanMethod = selectedMethod.indexOf('dhlpoland_') === 0 ? selectedMethod.substring(10) : selectedMethod;
            var isCod = isCodMethod(selectedMethod);

            var baseUrl = 'https://parcelshop.dhl.pl/mapa';
            var params = [];

            if (country !== 'PL') {
                params.push('country=' + encodeURIComponent(country));

                if (cleanMethod === 'connect_packstation' || cleanMethod === 'connect_packstation_cod') {
                    params.push('ptype=packStation');
                } else if (cleanMethod === 'connect_parcelshop' || cleanMethod === 'connect_parcelshop_cod') {
                    params.push('ptype=parcelShop');
                }
            } else {
                if (isCod) {
                    params.push('options_pickup_cod=T');
                }

                if (cleanMethod === 'economy_box' || cleanMethod === 'economy_box_cod') {
                    params.push('show_on_map_parcelshop=N&show_on_map_parcelshop_late=N&show_on_map_parcelstation=T');
                } else if (cleanMethod === 'economy_pop' || cleanMethod === 'economy_pop_cod') {
                    params.push('show_on_map_parcelshop=T&show_on_map_parcelshop_late=N&show_on_map_parcelstation=N');
                }
            }

            var iframeUrl = params.length ? baseUrl + '?' + params.join('&') : baseUrl;

            var $modalContainer = $('#ideo-dhl-admin-order-create-map-modal');
            if (!$modalContainer.length) {
                $modalContainer = $('<div id="ideo-dhl-admin-order-create-map-modal" style="display:none;"><iframe id="ideo-dhl-admin-order-create-map-iframe" style="width:100%; height:80vh; min-height:500px; border:none;"></iframe></div>');
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
                    activeModal = null;
                }
            };

            if (!$modalContainer.data('mageModal')) {
                modal(options, $modalContainer);
            }
            $modalContainer.modal('openModal');
            activeModal = $modalContainer.data('mageModal');
        });

        $(element).on('keyup change', '#dhl_postnummer_input', function () {
            var val = $(this).val();
            $('#dhl_postnummer').val(val);
        });

        var lastSelectedMethod = getSelectedMethodCode();

        $(element).on('change click', 'input[name="order[shipping_method]"]', function () {
            var newMethod = getSelectedMethodCode();
            if (newMethod && newMethod !== lastSelectedMethod) {
                $('#dhl_service_point_id').val('');
                $('#dhl_service_point_name').val('');
                $('#dhl_service_point_street').val('');
                $('#dhl_service_point_postcode').val('');
                $('#dhl_service_point_city').val('');
                $('#dhl_is_packstation').val('0');
                $('#dhl_is_postfiliale').val('0');
                $('#dhl_postnummer').val('');
                $('#dhl_postnummer_input').val('');
                $('.dhl-sp-display-text').text($t('None'));

                lastSelectedMethod = newMethod;
            }
            decorateDhlMethods();
            updatePointSelectorVisibility();
            togglePostnummerVisibility();
        });

        decorateDhlMethods();
        updatePointSelectorVisibility();
        updateDisplayTextFromHiddenInputs();
        togglePostnummerVisibility();
        initMessageListener();
    };
});
