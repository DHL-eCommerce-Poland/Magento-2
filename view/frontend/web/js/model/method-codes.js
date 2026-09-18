define([], function () {
    'use strict';

    return Object.freeze({
        CARRIER_CODE: 'dhlparcel',
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
        METHOD_ECONOMY: 'economy',
        METHOD_ECONOMY_COD: 'economy_cod',
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
        FULL_ECONOMY: 'dhlparcel_economy',
        FULL_ECONOMY_COD: 'dhlparcel_economy_cod',
        FULL_CONNECT_PARCELSHOP: 'dhlparcel_connect_parcelshop',
        FULL_CONNECT_PARCELSHOP_COD: 'dhlparcel_connect_parcelshop_cod',
        FULL_CONNECT_PACKSTATION: 'dhlparcel_connect_packstation',
        FULL_CONNECT_PACKSTATION_COD: 'dhlparcel_connect_packstation_cod'
    });
});
