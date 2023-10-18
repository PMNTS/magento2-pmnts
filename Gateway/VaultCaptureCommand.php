<?php
/**
 * Vault capture command
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PMNTS\Gateway\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Command\CommandException;
use PMNTS\Gateway\Helper\Data;
use PMNTS\Gateway\Model\GatewayFactory;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Psr\Log\LoggerInterface;

class VaultCaptureCommand extends AbstractCommand
{

    /**
     * @var \Magento\Vault\Api\PaymentTokenManagementInterface
     */
    private $tokenManagement;

    /**
     * VaultCaptureCommand constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $pmntsHelper
     * @param GatewayFactory $gatewayFactory
     * @param LoggerInterface $logger
     * @param PaymentTokenManagementInterface $tokenManagement
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Data $pmntsHelper,
        GatewayFactory $gatewayFactory,
        LoggerInterface $logger,
        PaymentTokenManagementInterface $tokenManagement
    ) {
        parent::__construct($scopeConfig, $pmntsHelper, $gatewayFactory, $logger);
        $this->tokenManagement = $tokenManagement;
    }

    /**
     * Perform a purchase against a saved card token.
     *
     * @param array $commandSubject
     * @return void
     * @throws \Magento\Payment\Gateway\Command\CommandException
     * @throws \Zend\Http\Client\Adapter\Exception\TimeoutException
     */
    public function execute(array $commandSubject): void
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
            $currencyCode = $order->getBaseCurrencyCode();
            $result = $gateway->tokenPurchase(
                $token->getGatewayToken(),
                $currencyCode,
                $commandSubject['amount'],
                $this->pmntsHelper->getOrderReference($order),
                null,
                $fraudData
            );

            if ($result && isset($result['response']) && $result['response']['successful'] === true) {
                $payment->setLastTransId($result['response']['transaction_id']);
                $payment->setTransactionId($result['response']['transaction_id']);
                $payment->setIsTransactionClosed(true);
            } else {
                $errors = isset($result['errors']) ? $result['errors'] : ['Gateway error'];
                $this->logger->critical(__(
                    'Vault payment error (Order #%1): %2',
                    $order->getIncrementId(),
                    implode('. ', $errors)
                ));
                throw new CommandException(__('Payment failed, please contact customer service.'));
            }
        } else {
            $this->logger->critical(__(
                "Unable to load token. Customer ID: %1. Public hash: %2",
                $customerId,
                $publicHash
            ));
            throw new CommandException(__('Unable to place order.'));
        }
    }
}
