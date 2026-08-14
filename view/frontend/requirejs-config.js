var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Ideo_Dhl/js/action/set-shipping-information-mixin': true
            },
            'Magento_Checkout/js/action/place-order': {
                'Ideo_Dhl/js/action/place-order-mixin': true
            },
            'Magento_Checkout/js/action/set-payment-information-and-place-order': {
                'Ideo_Dhl/js/action/set-payment-information-mixin': true
            }
        }
    }
};
