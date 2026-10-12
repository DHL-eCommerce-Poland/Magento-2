var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'DhlPoland_Dhl/js/action/set-shipping-information-mixin': true
            },
            'Magento_Checkout/js/action/place-order': {
                'DhlPoland_Dhl/js/action/place-order-mixin': true
            },
            'Magento_Checkout/js/view/shipping-information': {
                'DhlPoland_Dhl/js/view/summary/shipping-information-mixin': true
            },
            'Magento_Checkout/js/view/summary/shipping-information': {
                'DhlPoland_Dhl/js/view/summary/shipping-information-mixin': true
            },
            'Magento_Checkout/js/view/shipping': {
                'DhlPoland_Dhl/js/view/shipping-mixin': true
            }
        }
    }
};
