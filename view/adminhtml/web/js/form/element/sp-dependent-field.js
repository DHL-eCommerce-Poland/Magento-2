define([
    'Magento_Ui/js/form/element/abstract'
], function (Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            imports: {
                updateServiceType: '${ $.provider }:data.shipmentSettings.serviceType',
                updateMapPointType: '${ $.provider }:data.recipient.mapPointType'
            }
        },

        /**
         * Update element visibility based on shipment service type.
         * Visible when service type is 'SP' or 'EK'.
         *
         * @param {String} serviceType
         */
        updateServiceType: function (serviceType) {
            var st = serviceType ? String(serviceType).toUpperCase() : '';
            var isPointService = st === 'SP' || st === 'EK';
            this.visible(isPointService);
        },

        /**
         * Clear element value when international point type is set to 'courier'.
         *
         * @param {String} mapPointType
         */
        updateMapPointType: function (mapPointType) {
            if (mapPointType === 'courier') {
                this.value('');
            }
        }
    });
});
