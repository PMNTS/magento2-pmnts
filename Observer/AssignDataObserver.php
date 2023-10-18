<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;

class AssignDataObserver extends AbstractDataAssignObserver
{
    /**
     * Execute
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        $paymentInfo = $this->readPaymentModelArgument($observer);

        // Track whether a logged-in customer has opted to tokenize and store this card in the Vault
        if (array_key_exists('cc_save', $additionalData)) {
            $paymentInfo->setAdditionalInformation('pmnts_save_token', $additionalData['cc_save']);
        }
        if (array_key_exists('cc_token', $additionalData)) {
            $paymentInfo->setAdditionalInformation('pmnts_token', $additionalData['cc_token']);
        }
        $paymentInfo->setAdditionalInformation('type', $additionalData['type'] ?? null);
        if (isset($additionalData['pmnts_id']) && !empty($additionalData['pmnts_id'])) {
            $paymentInfo->setAdditionalInformation('pmnts_device_id', $additionalData['pmnts_id']);
        }
    }
}
