<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PMNTS\Gateway\Helper\Data as PmntsData;

class PmntsConfigProvider implements ConfigProviderInterface
{
    private const PMNTS_STR_KEY = "0123456789abcdefghijklmnopqrstuvwxyz";

    /**
     * @var string
     */
    protected $methodCode = PmntsData::METHOD_CODE;

    /**
     * @var \Magento\Payment\Model\MethodInterface
     */
    protected $method;

    /**
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var PaymentTokenManagement
     */
    protected $paymentTokenManagement;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Data $paymentHelper
     * @param CurrentCustomer $currentCustomer
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param ScopeConfigInterface $scopeConfig
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        Data $paymentHelper,
        CurrentCustomer $currentCustomer,
        PaymentTokenManagement $paymentTokenManagement,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->currentCustomer = $currentCustomer;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $config = [
            'payment' => [
                'pmntsGateway' => [
                    'iframeSrc' => $this->getIframeSrc(),
                    'fraudFingerprintSrc' => $this->getFraudFingerprintSource(),
                    'isSandbox' => $this->getIsSandbox(),
                    'canSaveCard' => $this->canSaveCard(),
                    'customerHasSavedCC' => $this->customerHasSavedCC(),
                    'ccVaultCode' => PmntsData::VAULT_METHOD_CODE
                ]
            ]
        ];
        return $config;
    }

    /**
     * Get Config values
     *
     * @param string $key
     * @return mixed
     */
    private function getConfigValue($key)
    {
        return $this->method->getConfigData($key);
    }

    /**
     * Get Fraud Finger print source
     *
     * @return string
     */
    private function getFraudFingerprintSource(): string
    {
        $is_sandbox = $this->getConfigValue("sandbox_mode");
        $username = $this->getConfigValue("username");
        if ($is_sandbox) {
            return "https://gateway.pmnts-sandbox.io/fraud/fingerprint/{$username}.js";
        } else {
            return "https://gateway.pmnts.io/fraud/fingerprint/{$username}.js";
        }
    }

    /**
     * Get Iframe URL
     *
     * @return string
     */
    private function getIframeSrc(): string
    {
        $is_sandbox = $this->getConfigValue("sandbox_mode");
        $username = $this->getConfigValue("username");
        $shared_secret = $this->getConfigValue("shared_secret");
        $nonce = substr(
            str_shuffle(
                str_repeat(self::PMNTS_STR_KEY, 5)
            ),
            0,
            5
        );
        $hash_payload = "{$nonce}:1.0:AUD";
        $hash = hash_hmac("md5", $hash_payload, $shared_secret ?? '');

        $base_url = "https://paynow.pmnts.io";
        if ($is_sandbox) {
            $base_url = "https://paynow.pmnts-sandbox.io";
        }

        $url = "{$base_url}/v2/{$username}/{$nonce}/AUD/1.0/{$hash}?show_extras=false&show_email=false&".
            "iframe=true&paypal=false&tokenize_only=true&masterpass=false&visacheckout=false&hide_button=true&".
            "postmessage=true&return_target=_self&ajax=true";

        // If CSS URL is set, generate signature, add to iframe URL
        $css_url = $this->getConfigValue("iframe_css");
        if (strlen($css_url) > 0) {
            $css_signature = hash_hmac("md5", $css_url, $shared_secret ?? '');
            $url = $url . "&css={$css_url}&css_signature={$css_signature}";
        }

        return $url;
    }

    /**
     * Get Sandbox mode
     *
     * @return mixed
     */
    private function getIsSandbox()
    {
        $is_sandbox = $this->getConfigValue("sandbox_mode");

        return $is_sandbox;
    }

    /**
     * Can save card
     *
     * @return bool
     */
    private function canSaveCard(): bool
    {
        $customer = $this->currentCustomer->getCustomerId();
        return $customer !== null && $this->scopeConfig->getValue('payment/pmnts_gateway_vault/active', 'stores');
    }

    /**
     * Check customer has saved CC
     *
     * @return bool
     */
    private function customerHasSavedCC(): bool
    {
        $customerId = $this->currentCustomer->getCustomerId();
        if (!isset($customerId)) {
            return false;
        }

        $customer = $this->currentCustomer->getCustomer();
        if ($customer === null) {
            return false;
        }
        $tokens = $this->paymentTokenManagement->getVisibleAvailableTokens($customerId);
        foreach ($tokens as $token) {
            if ($token->getPaymentMethodCode() === PmntsData::METHOD_CODE) {
                return true;
            }
        }
        return false;
    }
}
