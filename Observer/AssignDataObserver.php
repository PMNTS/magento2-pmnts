<?php

namespace PMNTS\Gateway\Observer;

class AssignDataObserver extends \Magento\Payment\Observer\AbstractDataAssignObserver
{
    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA);

        $paymentInfo = $this->readPaymentModelArgument($observer);

        // Track whether a logged-in customer has opted to tokenize and store this card in the Vault
        if (array_key_exists('cc_save', $additionalData)) {
            $paymentInfo->setAdditionalInformation('pmnts_save_token', $additionalData['cc_save']);
        }
        if (array_key_exists('cc_token', $additionalData)) {
            $paymentInfo->setAdditionalInformation('pmnts_token', $additionalData['cc_token']);
        }
        if (isset($additionalData['pmnts_id']) && !empty($additionalData['pmnts_id'])) {
            $paymentInfo->setAdditionalInformation('pmnts_device_id', $additionalData['pmnts_id']);
        }
    }
}