/**
 * MindArc_FatZebra Magento JS component
 *
 * @category    MindArc
 * @package     MindArc_FatZebra
 * @author      John Vella
 * @copyright   MindArc (http://mindarc.com.au)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
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
                type: 'mindarc_fatzebra',
                component: 'MindArc_FatZebra/js/view/payment/method-renderer/fatzebra-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);