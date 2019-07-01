<?php
/**
 * Vault capture command
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace PMNTS\Gateway\Gateway;

class VaultCaptureCommand extends AbstractCommand
{

    /**
     * @var \Magento\Vault\Api\PaymentTokenManagementInterface
     */
    private $tokenManagement;

    /**
     * VaultCaptureCommand constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \PMNTS\Gateway\Helper\Data $pmntsHelper
     * @param \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory
     * @param \Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PMNTS\Gateway\Helper\Data $pmntsHelper,
        \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory,
        \Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement
    ) {
        parent::__construct($scopeConfig, $pmntsHelper, $gatewayFactory);
        $this->tokenManagement = $tokenManagement;
    }

    /**
     * Perform a purchase against a saved card token.
     * @param array $commandSubject
     * @return void
     * @throws \Magento\Payment\Gateway\Command\CommandException
     * @throws \Zend\Http\Client\Adapter\Exception\TimeoutException
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
            /** @var  \PMNTS\Gateway\Model\Gateway $gateway */
            $gateway = $this->getGateway($storeId);
            $fraudData = $this->pmntsHelper->buildFraudPayload($order);

            $result = $gateway->token_purchase(
                $token->getGatewayToken(),
                $commandSubject['amount'],
                $this->pmntsHelper->getOrderReference($order),
                null,
                $fraudData
            );

            if ($result && isset($result['response']) && $result['response']['successful'] === true) {
                $payment->setLastTransId($result['response']['transaction_id']);
            }
        } else {
            throw new \Magento\Payment\Gateway\Command\CommandException(__('Unable to place order.'));
        }
    }
}
