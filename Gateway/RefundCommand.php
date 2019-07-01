<?php
/**
 * Refund command
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace PMNTS\Gateway\Gateway;

class RefundCommand extends AbstractCommand
{

    /**
     * @param array $commandSubject
     * @return void
     * @throws \Exception
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
        $response = $gateway->refund($payment->getLastTransId(), $commandSubject['amount'], $reference);
    }
}
