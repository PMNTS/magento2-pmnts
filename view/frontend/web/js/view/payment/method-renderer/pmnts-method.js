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
        'Magento_Checkout/js/view/payment/default',
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator',
        window.checkoutConfig.payment.pmntsGateway.fraudFingerprintSrc
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
              return window.checkoutConfig.payment.pmntsGateway.iframeSrc;
            },

            isIframeEnabled: function() {
              return window.checkoutConfig.payment.pmntsGateway.isIframeEnabled;
            },

            getFraudFingerprintSrc: function() {
              return window.checkoutConfig.payment.pmntsGateway.fraudFingerprintSrc;
            },

            getIsGatewaySandbox: function() {
              return window.checkoutConfig.payment.pmntsGateway.isSandbox;
            },

            pmntsPlaceOrder: function() {
              if (window.checkoutConfig.payment.pmntsGateway.isIframeEnabled) {
                // PostMessage
                var postMessageStrings = false;
                try{ window.postMessage({toString: function(){ postMessageStrings = true; }},"*") ;} catch(e){}

                var receiveMessage = function(event) {
                    if (event.origin.indexOf("paynow") === -1)  return;

                    var payload = event.data;
                    if (typeof event.data == 'string') {
                        if (/\[object/i.test(event.data)) {
                            alert("Sorry, it looks like there has been a problem communicating with your browsers...");
                        }
                        var pairs = payload.split("&");
                        payload = {};
                        for (var i = 0; i < pairs.length; i++) {
                            var element = pairs[i];
                            var kv = element.split("=");
                            payload[kv[0]] = kv[1];
                        }
                    }

                    if ('data' in payload) {
                        if (payload.data.message == "form.invalid") {
                            alert("Validation error: " + payload.data.errors);
                            return;
                        }
                        // Modern browser
                        // Use payload.data.x
                        jQuery("#pmnts_gateway-token").val(payload.data.token);
                        jQuery('#pmnts_gateway_cc_number').val(payload.data.card_number);
                        var expiryParts = payload.data.card_expiry.split('/');
                        jQuery('#pmnts_gateway_expiration').val(expiryParts[0]);
                        jQuery('#pmnts_gateway_expiration_yr').val(expiryParts[1]);
                        jQuery('#pmnts_gateway_cc_type').val('OT');
                        jQuery('#checkout-iframe').fadeOut();
                        jQuery('#default-place-order').click();
                    } else {
                        // Old browser don't use payload.data.x
                        if (payload.message == "form.invalid") {
                            alert("Validation error: " + payload.errors);
                            return;
                        }
                        jQuery("#pmnts_gateway-token").val(payload.token);
                        jQuery('#pmnts_gateway_cc_number').val(payload.card_number);
                        var expiryParts = payload.card_expiry.split('/');
                        jQuery('#pmnts_gateway_expiration').val(expiryParts[0]);
                        jQuery('#pmnts_gateway_expiration_yr').val(expiryParts[0])
                        jQuery('#pmnts_gateway_cc_type').val('OT');
                        jQuery('#checkout-iframe').fadeOut();
                        jQuery('#default-place-order').click();
                    }
                }
                window.addEventListener ? window.addEventListener("message", receiveMessage, false) : window.attachEvent("onmessage", receiveMessage);

                // Trigger the iframe
                var iframe = document.getElementById("checkout-iframe");
                iframe.contentWindow.postMessage('doCheckout', '*');

              } else {
                jQuery('#default-place-order').click();
              }
            },
            getData: function() {
              return {
                    'method': this.item.method,
                    'additional_data': {
                        "cc_cid":jQuery('#pmnts_gateway_cc_cid').val(),
                        "cc_ss_start_month":"",
                        "cc_ss_start_year":"",
                        "cc_type": jQuery('#pmnts_gateway_cc_type').val(),
                        "cc_exp_year":jQuery('#pmnts_gateway_expiration_yr').val(),
                        "cc_exp_month":jQuery('#pmnts_gateway_expiration').val(),
                        "cc_number":jQuery('#pmnts_gateway_cc_number').val(),
                        "cc_token": jQuery('#pmnts_gateway-token').val(),
                        "io_bb": jQuery("#io_bb").val()
                    }
                };
            }
        });
    }
);

var io_bbout_element_id = 'io_bb';
var io_enable_rip = true;
var io_install_flash = false;
var io_install_stm = false;
var io_exclude_stm = 12;
