define([
    'Magento_Customer/js/customer-data',
    'jquery'
], function (customerData, $) {
    'use strict';

    return function (config) {
        if (!config || !config.debug) {
            return;
        }

        console.log('%c📦 [DHL Debug Mode Enabled]', 'background: #d40511; color: #fff; font-weight: bold; font-size: 13px; padding: 4px 8px; border-radius: 3px;');

        var cart = customerData.get('cart');

        var logWeights = function () {
            var items = [];
            if (window.checkoutConfig && Array.isArray(window.checkoutConfig.quoteItemData) && window.checkoutConfig.quoteItemData.length > 0) {
                items = window.checkoutConfig.quoteItemData;
            } else {
                var cartData = cart();
                if (cartData && cartData.items) {
                    items = cartData.items;
                }
            }

            if (!items || items.length === 0) {
                return;
            }

            var totalWeight = 0;
            console.group('%c📊 DHL Cart Items Weight Breakdown', 'color: #d40511; font-weight: bold;');
            items.forEach(function (item, index) {
                var w = parseFloat(item.weight || (item.product && item.product.weight) || 0);
                var qty = parseInt(item.qty || 1);
                var itemTotal = w * qty;
                totalWeight += itemTotal;
                var name = item.name || item.product_name || '';
                var sku = item.sku || item.product_sku || '';
                console.log(
                    (index + 1) + '. ' + name + ' (' + sku + ')' +
                    ' | Weight/unit: ' + w + ' kg | Qty: ' + qty + ' | Total item weight: ' + itemTotal.toFixed(2) + ' kg'
                );
            });
            console.groupEnd();
            console.log(
                '%c TOTAL CART WEIGHT: ' + totalWeight.toFixed(2) + ' kg ',
                'background: #008800; color: #fff; font-weight: bold; font-size: 14px; padding: 4px 8px;'
            );
        };

        cart.subscribe(logWeights);
        logWeights();
    };
});
