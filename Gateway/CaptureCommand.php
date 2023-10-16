<?php
/**
 * Capture command (with tokenization if opted-in by customer)
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PMNTS\Gateway\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PMNTS\Gateway\Helper\Data;
use PMNTS\Gateway\Model\GatewayFactory;
use Psr\Log\LoggerInterface;
use Magento\Vault\Model\PaymentTokenFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;

class CaptureCommand extends AbstractCommand
{

    /**
     * @var PaymentTokenFactory
     */
    private $paymentTokenFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    private OrderPaymentExtensionInterfaceFactory $paymentExtensionInterfaceFactory;

    /**
     * @var string[]
     */
    public static $cardTypeMap = [
        'MasterCard' => 'MC',
        'VISA'       => 'VI',
        'AMEX'       => 'AE',
        'JCB'        => 'JCB'
    ];

    /**
     * CaptureCommand constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $pmntsHelper
     * @param GatewayFactory $gatewayFactory
     * @param LoggerInterface $logger
     * @param PaymentTokenFactory $paymentTokenFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param Json $json
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionInterfaceFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Data $pmntsHelper,
        GatewayFactory $gatewayFactory,
        LoggerInterface $logger,
        PaymentTokenFactory $paymentTokenFactory,
        CustomerRepositoryInterface $customerRepository,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Json $json,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionInterfaceFactory
    ) {
        parent::__construct($scopeConfig, $pmntsHelper, $gatewayFactory, $logger);
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->customerRepository = $customerRepository;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->json = $json;
        $this->paymentExtensionInterfaceFactory = $paymentExtensionInterfaceFactory;
    }

    /**
     * Execute
     *
     * @param array $commandSubject
     * @return \Magento\Payment\Gateway\Command\ResultInterface|void|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
        $paymentType = $payment->getAdditionalInformation('type');
        $currencyCode = $order->getBaseCurrencyCode();
        /** @var array $result */
        $result = $gateway->tokenPurchase(
            $pmntsToken,
            $currencyCode,
            $commandSubject['amount'],
            $reference,
            null,
            $fraudData,
            $paymentType
        );

        if ($result && isset($result['response'])) {
            /**
             * fixing issue for GooglePay responses status as successful key comes under results array
             * but successful key comes under results[response] array for FatZebra
             * This now works for both FatZebra and GooglePay
             */
            if ($result['response']['successful'] === true && $result['successful'] === true) {
                if (!empty($result['response']['transaction_id'])) {
                    $payment->setLastTransId($result['response']['transaction_id']);
                    $payment->setTransactionId($result['response']['transaction_id']);
                    $payment->setIsTransactionClosed(true);
                } else {
                    $this->logger->alert(
                        __('[FATZEBRA][CaptureCommand] transaction_id
                        is missing from the response object for order : '.
                            $order->getIncrementId())
                    );
                }
            } else {
                $errors = isset($result['errors']) ? $result['errors'] : ['Gateway error'];
                $this->logger->critical(
                    __(
                        '[FATZEBRA][CaptureCommand] Payment error (Order #%1): %2',
                        $order->getIncrementId(),
                        implode('. ', $errors)
                    )
                );
                throw new \Magento\Framework\Validator\Exception(
                    __('Payment failed, please contact customer service.')
                );
            }
        } else {
            throw new \Magento\Framework\Validator\Exception(
                __('Payment gateway error, please contact customer service.')
            );
        }

        if ($payment->getAdditionalInformation('pmnts_save_token') && $order->getCustomerId()) {
            try {
                $paymentTokenDetails = $this->getTokenDetails($result['response']);
            } catch (\Exception $ex) {
                $this->logger->alert('[FATZEBRA][CaptureCommand]' . $ex->getMessage());
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
     * Get token details
     *
     * @param array $response
     * @return array
     * @throws \Exception
     */
    protected function getTokenDetails($response): array
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
