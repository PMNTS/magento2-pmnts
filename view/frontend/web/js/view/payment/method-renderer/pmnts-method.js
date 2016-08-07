/**
 * PMNTS_Gateway Magento JS component
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
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
                template: 'pmnts_gateway/payment/pmnts-form'
            },

            getCode: function() {
                return 'pmnts_gateway';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            getIframeUrl: function() {
              window.checkoutConfig.payment.pmntsGateway.getIframeSrc;
            },

            isIframeEnabled: function() {
              window.checkoutConfig.payment.pmntsGateway.getIframeEnabled;
            },

            getFraudFingerprintSrc: function() {
              window.checkoutConfig.payment.pmntsGateway.fraudFingerprintSrc
            }
        });
    }
);
