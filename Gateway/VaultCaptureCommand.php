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

use Psr\Log\LoggerInterface;

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
     * @param LoggerInterface $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $crypt
     * @param \Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PMNTS\Gateway\Helper\Data $pmntsHelper,
        \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $crypt,
        \Magento\Vault\Api\PaymentTokenManagementInterface $tokenManagement
    ) {
        parent::__construct($scopeConfig, $pmntsHelper, $gatewayFactory, $logger, $crypt);
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

        $publicHash = $payment->getAdditionalInformation('public_hash');
        $customerId = $payment->getAdditionalInformation('customer_id');

        $token = $this->tokenManagement->getByPublicHash(
            $publicHash,
            $customerId
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
            } else {
                $errors = isset($result['errors']) ? $result['errors'] : ['Gateway error'];
                $this->logger->critical(__(
                    'Vault payment error (Order #%1): %2',
                    $order->getIncrementId(),
                    implode('. ', $errors)
                ));
                throw new \Magento\Payment\Gateway\Command\CommandException(__('Payment failed, please contact customer service.'));
            }
        } else {
            $this->logger->critical(__(
                "Unable to load token. Customer ID: %1. Public hash: %2",
                $customerId,
                $publicHash
            ));
            throw new \Magento\Payment\Gateway\Command\CommandException(__('Unable to place order.'));
        }
    }
}
