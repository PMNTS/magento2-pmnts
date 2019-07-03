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
      'Magento_Checkout/js/model/full-screen-loader',
      'Magento_Vault/js/view/payment/vault-enabler'
  ],
  function (Component, $, validator, messageList, fullScreenLoader, VaultEnabler) {
        'use strict';

        window.pmntsGateway.messageList = messageList;
        window.pmntsGateway.fullScreenLoader = fullScreenLoader;

        return Component.extend({
            defaults: {
                template: 'PMNTS_Gateway/payment/pmnts-form'
            },

            initialize: function() {
                this._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
            },

            /**
             * @returns {String}
             */
            getVaultCode: function () {
                return window.checkoutConfig.payment['pmntsGateway'].ccVaultCode;
            },

            getCode: function() {
                return 'pmnts_gateway';
            },

            isActive: function() {
                return true;
            },

            getIframeUrl: function() {
              return window.checkoutConfig.payment.pmntsGateway.iframeSrc;
            },

            canSaveCard: function() {
              return window.checkoutConfig.payment.pmntsGateway.canSaveCard;
            },

            customerHasSavedCC: function() {
              return window.checkoutConfig.payment.pmntsGateway.customerHasSavedCC;
            },

            pmntsPlaceOrder: function() {
                window.pmntsGateway.processIframeOrder();
            },

            getData: function() {
                var data = {
                    'method': this.item.method,
                    'additional_data': {
                        "cc_token": jQuery('#pmnts_gateway-token').val(),
                        "cc_save": jQuery("#pmnts_gateway_cc_save").is(':checked')
                    }
                };

                this.vaultEnabler.visitAdditionalData(data);
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
    jQuery('#pmnts-place-token-order').click();
  },
  messageList: null,
  fullScreenLoader: null
};

window.pmntsGateway.attachEvents();
