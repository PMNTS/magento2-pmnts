<?php

namespace PMNTS\Gateway\Gateway;

use Magento\Payment\Gateway\Command;
use Magento\Payment\Gateway\Command\CommandException;

class RefundCommand extends AbstractCommand
{

    /**
     * @param array $commandSubject
     * @return null|Command\ResultInterface
     * @throws CommandException
     */
    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        /** @var \FatZebra\Gateway $gateway */
        $gateway = $this->getGateway($payment->getOrder()->getStoreId());
        // At this point, we don't have a CreditMemo increment ID, so append a timestamp to ensure uniqueness in the event
        // of multiple refunds against a single invoice.
        $reference = $this->pmntsHelper->getOrderReference($payment->getOrder()) . '-R-' . (new \DateTime())->format('ymdhi');
        $gateway->refund($payment->getLastTransId(), $commandSubject['amount'], $reference);
    }
}