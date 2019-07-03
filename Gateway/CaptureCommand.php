<?php
/**
 * Capture command (with tokenization if opted-in by customer)
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace PMNTS\Gateway\Gateway;

class CaptureCommand extends AbstractCommand
{

    /** @var \Magento\Vault\Model\PaymentTokenFactory */
    private $paymentTokenFactory;

    /** @var \Magento\Customer\Api\CustomerRepositoryInterface */
    private $customerRepository;

    /** @var \Magento\Vault\Api\PaymentTokenRepositoryInterface */
    private $paymentTokenRepository;

    /** @var \Magento\Framework\Serialize\Serializer\Json */
    private $json;

    /** @var \Magento\Framework\Encryption\EncryptorInterface */
    private $encryptor;

    /** @var \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory */
    private $paymentExtensionInterfaceFactory;

    public static $cardTypeMap = [
        'MasterCard' => 'MC',
        'VISA'       => 'VI',
        'AMEX'       => 'AE',
        'JCB'        => 'JCB'
    ];

    /**
     * CaptureCommand constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \PMNTS\Gateway\Helper\Data $pmntsHelper
     * @param \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $crypt
     * @param \Magento\Vault\Model\PaymentTokenFactory $paymentTokenFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Vault\Api\PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param \Magento\Framework\Serialize\Serializer\Json $json
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory $paymentExtensionInterfaceFactory
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PMNTS\Gateway\Helper\Data $pmntsHelper,
        \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $crypt,
        \Magento\Vault\Model\PaymentTokenFactory $paymentTokenFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Vault\Api\PaymentTokenRepositoryInterface $paymentTokenRepository,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory $paymentExtensionInterfaceFactory
    ) {
        parent::__construct($scopeConfig, $pmntsHelper, $gatewayFactory, $logger, $crypt);
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->customerRepository = $customerRepository;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->json = $json;
        $this->encryptor = $encryptor;
        $this->paymentExtensionInterfaceFactory = $paymentExtensionInterfaceFactory;
    }

    /**
     * @param array $commandSubject
     * @return void
     * @throws \Magento\Framework\Validator\Exception
     */
    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $pmntsToken = $payment->getAdditionalInformation('pmnts_token');

        /** @var \PMNTS\Gateway\Model\Gateway $gateway */
        $gateway = $this->getGateway($order->getStoreId());

        $reference = $this->pmntsHelper->getOrderReference($order);
        $fraudData = $this->pmntsHelper->buildFraudPayload($order);

        /** @var array $result */
        $result = $gateway->token_purchase($pmntsToken, $commandSubject['amount'], $reference, null, $fraudData);

        if ($result && isset($result['response'])) {
            if ($result['response']['successful'] === false) {
                $errors = isset($result['errors']) ? $result['errors'] : ['Gateway error'];
                $this->logger->critical(__(
                    'Payment error (Order #%1): %2',
                    $order->getIncrementId(),
                    implode('. ', $errors)
                ));
                throw new \Magento\Framework\Validator\Exception(__('Payment failed, please contact customer service.'));
            }
        } else {
            throw new \Magento\Framework\Validator\Exception(__('Payment gateway error, please contact customer service.'));
        }

        if ($payment->getAdditionalInformation('pmnts_save_token') && $order->getCustomerId()) {
            try {
                $paymentTokenDetails = $this->getTokenDetails($result['response']);
            } catch (\Exception $ex) {
                // If the response from the gateway does not conform to the spec, give up on Vault storage
                return;
            }

            /** @var \Magento\Vault\Model\PaymentToken $paymentToken */
            $paymentToken = $this->paymentTokenFactory->create();
            $paymentToken->setType(\Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
            $paymentToken->setTokenDetails($this->json->serialize($paymentTokenDetails));
            $paymentToken->setExpiresAt(new \DateTime($result['response']['card_expiry']));
            $paymentToken->setGatewayToken($pmntsToken);
            /** @var \Magento\Sales\Api\Data\OrderPaymentExtension $extension */
            $paymentExtension = $this->paymentExtensionInterfaceFactory->create();
            $paymentExtension->setVaultPaymentToken($paymentToken);
            $payment->setExtensionAttributes($paymentExtension);
        } else {
            // If the customer has not opted into the token storage, do not persist it to the database
            $payment->unsAdditionalInformation('pmnts_token');
        }
    }

    /**
     * @param array $response
     * @return array
     * @throws \Exception
     */
    protected function getTokenDetails($response)
    {
        $expirationDate = (new \DateTime($response['card_expiry']))->format('m/y');
        $cardType = $response['card_type'];
        if (array_key_exists($cardType, self::$cardTypeMap)) {
            $cardType = self::$cardTypeMap[$cardType];
        }
        return [
            'maskedCC' => substr($response['card_number'], -4, 4),
            'expirationDate' => $expirationDate,
            'type' => $cardType
        ];
    }
}
