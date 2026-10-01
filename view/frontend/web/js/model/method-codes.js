define([], function () {
    'use strict';

    return Object.freeze({
        CARRIER_CODE: 'dhlpoland',
        DEFAULT_MAP_URL: 'https://parcelshop.dhl.pl/mapa',

        // Method codes
        METHOD_PARCEL_POLSKA: 'parcel_polska',
        METHOD_PARCEL_POLSKA_COD: 'parcel_polska_cod',
        METHOD_PREMIUM: 'premium',
        METHOD_PREMIUM_COD: 'premium_cod',
        METHOD_PARCEL_9: 'parcel_9',
        METHOD_PARCEL_9_COD: 'parcel_9_cod',
        METHOD_PARCEL_12: 'parcel_12',
        METHOD_PARCEL_12_COD: 'parcel_12_cod',
        METHOD_ECONOMY_ALL: 'economy_all',
        METHOD_ECONOMY_ALL_COD: 'economy_all_cod',
        METHOD_ECONOMY_POP: 'economy_pop',
        METHOD_ECONOMY_POP_COD: 'economy_pop_cod',
        METHOD_ECONOMY_BOX: 'economy_box',
        METHOD_ECONOMY_BOX_COD: 'economy_box_cod',
        METHOD_MAX_PALETA: 'max_paleta',
        METHOD_MAX_PALETA_COD: 'max_paleta_cod',
        METHOD_CONNECT: 'connect',
        METHOD_CONNECT_COD: 'connect_cod',
        METHOD_CONNECT_PARCELSHOP: 'connect_parcelshop',
        METHOD_CONNECT_PARCELSHOP_COD: 'connect_parcelshop_cod',
        METHOD_CONNECT_PACKSTATION: 'connect_packstation',
        METHOD_CONNECT_PACKSTATION_COD: 'connect_packstation_cod',
        METHOD_CONNECT_PLUS: 'connect_plus',
        METHOD_CONNECT_PLUS_COD: 'connect_plus_cod',
        METHOD_CONNECT_PALLET: 'connect_pallet',
        METHOD_CONNECT_PALLET_COD: 'connect_pallet_cod',

        // Full method codes (carrier_method)
        FULL_ECONOMY_ALL: 'dhlpoland_economy_all',
        FULL_ECONOMY_ALL_COD: 'dhlpoland_economy_all_cod',
        FULL_ECONOMY_POP: 'dhlpoland_economy_pop',
        FULL_ECONOMY_POP_COD: 'dhlpoland_economy_pop_cod',
        FULL_ECONOMY_BOX: 'dhlpoland_economy_box',
        FULL_ECONOMY_BOX_COD: 'dhlpoland_economy_box_cod',
        FULL_CONNECT_PARCELSHOP: 'dhlpoland_connect_parcelshop',
        FULL_CONNECT_PARCELSHOP_COD: 'dhlpoland_connect_parcelshop_cod',
        FULL_CONNECT_PACKSTATION: 'dhlpoland_connect_packstation',
        FULL_CONNECT_PACKSTATION_COD: 'dhlpoland_connect_packstation_cod'
    });
});
