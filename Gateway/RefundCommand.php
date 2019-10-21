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
        /** @var \PMNTS\Gateway\Model\Gateway $gateway */
        $gateway = $this->getGateway($payment->getOrder()->getStoreId());
        // At this point, we don't have a CreditMemo increment ID, so append a timestamp to ensure uniqueness in the event
        // of multiple refunds against a single invoice.
        $reference = $this->pmntsHelper->getOrderReference($payment->getOrder()) . '-R-' . (new \DateTime())->format('ymdhi');
        $response = $gateway->refund($payment->getLastTransId(), $commandSubject['amount'], $reference);
        if (is_array($response) && array_key_exists('successful', $response)) {
            if ($response['successful'] === true) {
                $payment->setLastTransId($response['response']['transaction_id']);
            } else {
                $errors = array_key_exists('errors', $response) ? implode('. ', $response['errors']) : 'Unknown gateway error';
                $this->logger->critical(__('Refund failed for Order #%1. %2', $payment->getOrder()->getIncrementId()), $errors);
                throw new \Exception(__('Refund failed: %1', $errors));
            }
        }
    }
}
