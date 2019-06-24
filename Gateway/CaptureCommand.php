<?php

namespace PMNTS\Gateway\Gateway;

class CaptureCommand extends AbstractCommand
{

    /**
     * @var \Magento\Vault\Model\PaymentTokenFactory
     */
    private $paymentTokenFactory;
    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var \Magento\Vault\Api\PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    public static $cardTypeMap = [
        'MasterCard' => 'MC',
        'VISA'       => 'VI',
        'AMEX'       => 'AE'
    ];

    /**
     * CaptureCommand constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \PMNTS\Gateway\Helper\Data $pmntsHelper
     * @param \Magento\Vault\Model\PaymentTokenFactory $paymentTokenFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Vault\Api\PaymentTokenRepositoryInterface $paymentTokenRepository
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PMNTS\Gateway\Helper\Data $pmntsHelper,
        \Magento\Vault\Model\PaymentTokenFactory $paymentTokenFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Vault\Api\PaymentTokenRepositoryInterface $paymentTokenRepository
    ) {
        parent::__construct($scopeConfig, $pmntsHelper);
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->customerRepository = $customerRepository;
        $this->paymentTokenRepository = $paymentTokenRepository;
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
        }

        if ($payment->getAdditionalInformation('pmnts_save_token') && $order->getCustomerId()) {
            $paymentTokenDetails = $this->getTokenDetails($result);

            try {
                $customer = $this->customerRepository->getById($order->getCustomerId());
            } catch (\Magento\Framework\Exception\NoSuchEntityException $ex) {
                $customer = null;
            }
            /** @var \Magento\Vault\Model\PaymentToken $paymentToken */
            $paymentToken = $this->paymentTokenFactory->create();
            $paymentToken->setCustomerId($customer->getId());
            $paymentToken->setType(\Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
            $paymentToken->setPaymentMethodCode(\PMNTS\Gateway\Helper\Data::METHOD_CODE);

            $this->paymentTokenRepository->save($paymentToken);
        }
    }

    /**
     * @param $gatewayResponse
     * @return array
     */
    protected function getTokenDetails($gatewayResponse)
    {
        $expirationDate = (new \DateTime($gatewayResponse->card_expiry))->format('m/y');
        $cardType = $gatewayResponse['card_type'];
        if (array_key_exists($cardType, self::$cardTypeMap)) {
            $cardType = self::$cardTypeMap[$cardType];
        }
        return [
            'maskedCC' => substr($gatewayResponse->card_number, -4, 4),
            'card_expiry' => $expirationDate,
            'type' => $cardType
        ];
    }
}
