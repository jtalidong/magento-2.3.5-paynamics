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
                type: 'pnx',
                component: 'Paynamics_Gateway/js/view/payment/method-renderer/pnx-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
