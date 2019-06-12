/*browser:true*/
/*global define*/
define([
    'jquery',
    'Magento_Vault/js/view/payment/method-renderer/vault',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/full-screen-loader'
], function ($, VaultComponent, globalMessageList, fullScreenLoader) {
    'use strict';

    return VaultComponent.extend({
        defaults: {
            template: 'PMNTS_Gateway/payment/pmnts_vault_form'
        },

        /**
         * Get masked card
         * @returns {String}
         */
        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        /**
         * Get expiration date
         * @returns {String}
         */
        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        /**
         * Get card type
         * @returns {String}
         */
        getCardType: function () {
            return this.details.type;
        },

        /**
         * Get public hash
         * @returns {String}
         */
        getToken: function() {
            return this.publicHash;
        }
    });
});
