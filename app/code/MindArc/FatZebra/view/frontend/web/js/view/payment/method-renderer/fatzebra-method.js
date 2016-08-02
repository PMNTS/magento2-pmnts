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
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'MindArc_FatZebra/payment/fatzebra-form'
            },

            getCode: function() {
                return 'mindarc_fatzebra';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
