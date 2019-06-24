<?php

namespace PMNTS\Gateway\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Command\CommandException;

class VaultCaptureCommand extends AbstractCommand
{

    /**
     * @var \Magento\Vault\Api\PaymentTokenManagementInterface
     */
    private $tokenManagement;

    /**
     * VaultCaptureCommand constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param \PMNTS\Gateway\Helper\Data $pmntsHelper
     * @param \Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        \PMNTS\Gateway\Helper\Data $pmntsHelper,
        \Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement
    ) {
        parent::__construct($scopeConfig, $pmntsHelper);
        $this->tokenManagement = $tokenManagement;
    }

    /**
     * Perform a purchase against a saved card token.
     * @param array $commandSubject
     * @return void
     * @throws CommandException
     * @throws \FatZebra\TimeoutException
     */
    public function execute(array $commandSubject)
    {
        /** @var  \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment']->getPayment();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        $token = $this->tokenManagement->getByPublicHash(
            $payment->getAdditionalInformation('public_hash'),
            $payment->getAdditionalInformation('customer_id')
        );

        if ($token) {
            /** @var  \FatZebra\Gateway $gateway */
            $gateway = $this->getGateway($storeId);
            $fraudData = $this->pmntsHelper->buildFraudPayload($order);

            $result = $gateway->token_purchase(
                $token->getGatewayToken(),
                $commandSubject['amount'],
                $this->pmntsHelper->getOrderReference($order),
                null,
                $fraudData
            );

            if ($result && $result->response->successful === true) {
                $payment->setLastTransId($result->response->transaction_id);
            }
        } else {
            throw new \Magento\Payment\Gateway\Command\CommandException(__('Unable to place order.'));
        }
    }
}
