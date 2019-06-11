<?php

namespace PMNTS\Gateway\Gateway;

use Magento\Payment\Gateway\Command;
use Magento\Payment\Gateway\Command\CommandException;

class CaptureCommand extends AbstractCommand
{
    /**
     * @param array $commandSubject
     * @return null|Command\ResultInterface
     * @throws CommandException
     * @throws \Exception
     */
    public function execute(array $commandSubject)
    {
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();

        $pmntsToken = $payment->getAdditionalInformation('pmnts_token');

        $gateway = $this->getGateway($order->getStoreId());

        $reference = "TestOrder-" . $order->getIncrementId();
        $fraudData = [];

        /** @var \StdClass $result */
        $result = $gateway->token_purchase($pmntsToken, $commandSubject['amount'], $reference, null, $fraudData);
        if ($result && ($response = $result->response)) {
           if ($response->successful === false) {
               $errorMsg = $response->message ?: 'Unknown gateway error.';
               if (is_array($result->errors) && count($result->errors) > 0) {
                   $errorMsg = join('. ', $result->errors);
               }
               throw new \Magento\Framework\Validator\Exception(__('Payment error: %s.', $errorMsg));
           }
        }


        // TODO:
        //   Store last4
        //   If save card is selected, store token into vault
        if ($payment->getAdditionalInformation('pmnts_save_token')) {
            // Store token into vault
        }
    }
}