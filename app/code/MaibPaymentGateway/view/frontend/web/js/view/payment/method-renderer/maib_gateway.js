/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Magento_MaibPaymentGateway/payment/form',
                transactionResult: ''
            },

            getCode: function() {
                return 'maib_gateway';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                };
            },
        });
    }
);