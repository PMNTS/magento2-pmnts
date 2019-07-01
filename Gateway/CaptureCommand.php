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

    public static $cardTypeMap = [
        'MasterCard' => 'MC',
        'VISA'       => 'VI',
        'AMEX'       => 'AE',
        'JCB'        => 'JCB'
    ];
    /**
     * @var \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory
     */
    private $paymentExtensionInterfaceFactory;

    /**
     * CaptureCommand constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \PMNTS\Gateway\Helper\Data $pmntsHelper
     * @param \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory
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
        \Magento\Vault\Model\PaymentTokenFactory $paymentTokenFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Vault\Api\PaymentTokenRepositoryInterface $paymentTokenRepository,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory $paymentExtensionInterfaceFactory
    ) {
        parent::__construct($scopeConfig, $pmntsHelper, $gatewayFactory);
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->customerRepository = $customerRepository;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->json = $json;
        $this->encryptor = $encryptor;
        $this->paymentExtensionInterfaceFactory = $paymentExtensionInterfaceFactory;
    }

    /**
     * @param array $commandSubject
     * @return null|\Magento\Payment\Gateway\Command\ResultInterface
     * @throws \Magento\Payment\Gateway\Command\CommandException
     * @throws \Exception
     */
    public function execute(array $commandSubject)
    {
        $payment = $commandSubject['payment']->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $pmntsToken = $payment->getAdditionalInformation('pmnts_token');

        /** @var \PMNTS\Gateway\Model\Gateway $gateway */
        $gateway = $this->getGateway($order->getStoreId());

        $reference = $this->pmntsHelper->getOrderReference($order);
        $fraudData = $this->pmntsHelper->buildFraudPayload($order);

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
        } else {
            throw new \Magento\Framework\Validator\Exception(__('Payment gateway error, please contact customer service.'));
        }

        if ($payment->getAdditionalInformation('pmnts_save_token') && $order->getCustomerId()) {
            $paymentTokenDetails = $this->getTokenDetails($result->response);

            /** @var \Magento\Vault\Model\PaymentToken $paymentToken */
            $paymentToken = $this->paymentTokenFactory->create();
            $paymentToken->setType(\Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
            $paymentToken->setTokenDetails($this->json->serialize($paymentTokenDetails));
            $paymentToken->setExpiresAt(new \DateTime($result->response->card_expiry));
            $paymentToken->setGatewayToken($pmntsToken);
            /** @var \Magento\Sales\Api\Data\OrderPaymentExtension $extension */
            $paymentExtension = $this->paymentExtensionInterfaceFactory->create();
            $paymentExtension->setVaultPaymentToken($paymentToken);
            $payment->setExtensionAttributes($paymentExtension);
        }
    }

    /**
     * @param $gatewayResponse
     * @return array
     */
    protected function getTokenDetails($gatewayResponse)
    {
        $expirationDate = (new \DateTime($gatewayResponse->card_expiry))->format('m/y');
        $cardType = $gatewayResponse->card_type;
        if (array_key_exists($cardType, self::$cardTypeMap)) {
            $cardType = self::$cardTypeMap[$cardType];
        }
        return [
            'maskedCC' => substr($gatewayResponse->card_number, -4, 4),
            'expirationDate' => $expirationDate,
            'type' => $cardType
        ];
    }
}
