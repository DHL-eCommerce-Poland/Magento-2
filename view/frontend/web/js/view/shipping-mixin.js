define([
    'jquery'
], function ($) {
    'use strict';

    return function (Shipping) {
        return Shipping.extend({
            initialize: function () {
                this._super();

                if (this.rates) {
                    this.rates.subscribe(this.appendDhlLogos.bind(this));
                }

                $(document).ready(this.appendDhlLogos.bind(this));
                setTimeout(this.appendDhlLogos.bind(this), 100);
                setTimeout(this.appendDhlLogos.bind(this), 500);

                return this;
            },

            appendDhlLogos: function () {
                setTimeout(function () {
                    var dhlConfig = window.checkoutConfig && window.checkoutConfig.shipping && window.checkoutConfig.shipping.dhlparcel;
                    var logos = dhlConfig && dhlConfig.logos;
                    if (!logos) {
                        return;
                    }

                    $('input[value^="dhlparcel_"]').each(function () {
                        var $input = $(this);
                        var methodCode = $input.val();
                        var logoUrl = logos[methodCode] || logos.default;
                        if (!logoUrl) {
                            return;
                        }

                        var $row = $input.closest('tr');
                        if (!$row.length) {
                            return;
                        }

                        var $targetCell = $row.find('.col-carrier');
                        if (!$targetCell.length) {
                            $targetCell = $row.find('.col-method').last();
                        }

                        if ($targetCell.length && !$targetCell.find('.dhl-method-logo').length) {
                            $targetCell.append(
                                '<img src="' + logoUrl + '" class="dhl-method-logo" alt="" aria-hidden="true" decoding="async" height="22">'
                            );
                        }
                    });
                }, 50);
            }
        });
    };
});
