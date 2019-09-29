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
                type: 'parts_payment',
                component: 'CodeCustom_Payments/js/view/parts_payment/method-renderer/parts_payment'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);