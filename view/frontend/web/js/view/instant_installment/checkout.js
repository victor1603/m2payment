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
                type: 'instant_installment',
                component: 'CodeCustom_Payments/js/view/instant_installment/method-renderer/instant_installment'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);