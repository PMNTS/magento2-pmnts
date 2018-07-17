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
      'Magento_Payment/js/model/credit-card-validation/validator',
      'Magento_Ui/js/model/messageList',
      'Magento_Checkout/js/model/full-screen-loader'
  ],
  function (Component, $, validator, messageList, fullScreenLoader) {
        'use strict';

        window.pmntsGateway.messageList = messageList;
        window.pmntsGateway.fullScreenLoader = fullScreenLoader;

        return Component.extend({
            defaults: {
                template: 'PMNTS_Gateway/payment/pmnts-form'
            },

            getCode: function() {
                return 'pmnts_gateway';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                if (this.isIframeEnabled()) { return true; }
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

            canSaveCard: function() {
              return window.checkoutConfig.payment.pmntsGateway.canSaveCard;
            },

            customerHasSavedCC: function() {
              return window.checkoutConfig.payment.pmntsGateway.customerHasSavedCC;
            },

            pmntsPlaceOrder: function() {
              if (window.checkoutConfig.payment.pmntsGateway.isIframeEnabled) {
                window.pmntsGateway.processIframeOrder();
              } else {
                jQuery('#default-place-order').click();
              }
            },
            getData: function() {
              var data = {
                    'method': this.item.method,
                    'additional_data': {
                        "cc_cid":jQuery('#pmnts_gateway_cc_cid').val(),
                        "cc_ss_start_month":"",
                        "cc_ss_start_year":"",
                        "cc_type": jQuery('#pmnts_gateway_cc_type').val(),
                        "cc_exp_year":jQuery('#pmnts_gateway_expiration_yr').val(),
                        "cc_exp_month":jQuery('#pmnts_gateway_expiration').val(),
                        "cc_token": jQuery('#pmnts_gateway-token').val(),
                        "cc_save": jQuery("#pmnts_gateway_cc_save").is(':checked'),
                        "pmnts_id": jQuery("#pmnts_id").val()
                    }
                };

                if (!window.checkoutConfig.payment.pmntsGateway.isIframeEnabled) {
                  data['additional_data']["cc_number"] = jQuery('#pmnts_gateway_cc_number').val();
                }
              return data;
            }
        });
    }
);

setTimeout(function() {
  var s = document.createElement( 'script' );
  s.setAttribute( 'src', window.checkoutConfig.payment.pmntsGateway.fraudFingerprintSrc );
  document.body.appendChild( s );
}, 1000);


window.pmntsGateway = {
  attachEvents: function() {
    // Clear existing events...
    window.removeEventListener ? window.removeEventListener("message", window.pmntsGateway.receiveMessage, false) : window.detatchEvent("onmessage", window.pmntsGateway.receiveMessage);
    // And add...
    window.addEventListener ? window.addEventListener("message", window.pmntsGateway.receiveMessage, false) : window.attachEvent("onmessage", window.pmntsGateway.receiveMessage);
  },
  processIframeOrder: function() {
    // PostMessage
    var postMessageStrings = false;
    try{ window.postMessage({toString: function(){ postMessageStrings = true; }},"*") ;} catch(e){}

    // Trigger the iframe
    var iframe = document.getElementById("checkout-iframe");
    window.pmntsGateway.fullScreenLoader.startLoader();
    iframe.contentWindow.postMessage('doCheckout', '*');
  },
  cardTypeMap: function(gwType) {
    var types = {
      visa: 'VI',
      mastercard: 'MC',
      amex: 'AE',
      jcb: 'JCB'
    }

    return types[gwType.toLowerCase()];
  },
  receiveMessage: function(event) {
      if (event.origin.indexOf("paynow") === -1)  return;
      window.pmntsGateway.fullScreenLoader.stopLoader();

      var payload = event.data;
      if (typeof event.data == 'string') {
          if (/\[object/i.test(event.data)) {
              window.pmntsGateway.messageList.addErrorMessage({message: "Sorry, it looks like there has been a problem communicating with your browsers..."});
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
              window.pmntsGateway.messageList.addErrorMessage({message: "Validation error: " + payload.data.errors});
              return;
          }
          // Modern browser
          // Use payload.data.x
          window.pmntsGateway.fillInPaymentForm(payload.data);
      } else {
          // Old browser don't use payload.data.x
          if (payload.message == "form.invalid") {
              window.pmntsGateway.messageList.addErrorMessage({message: "Validation error: " + payload.errors});
              return;
          }
          window.pmntsGateway.fillInPaymentForm(payload);
      }
  },
  fillInPaymentForm: function(data) {
    jQuery("#pmnts_gateway-token").val(data.token);
    jQuery('#pmnts_gateway_cc_number').val(data.card_number);
    var expiryParts = data.card_expiry.split('/');
    jQuery('#pmnts_gateway_expiration').val(expiryParts[0]);
    jQuery('#pmnts_gateway_expiration_yr').val(expiryParts[1]);
    jQuery('#pmnts_gateway_cc_type').val(window.pmntsGateway.cardTypeMap(data.card_type));
    jQuery('#default-place-order').click();
  },
  messageList: null,
  fullScreenLoader: null
};

window.pmntsGateway.attachEvents();
