/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'maib_gateway',
                component: 'Magento_MaibPaymentGateway/js/view/payment/method-renderer/maib_gateway'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
