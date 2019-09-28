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
                type: 'liqpay_payment',
                component: 'CodeCustom_Payments/js/view/liqpay/method-renderer/liqpay'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);