<?php

namespace PMNTS\Gateway\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use \Magento\Customer\Helper\Session\CurrentCustomer;

class PmntsConfigProvider implements ConfigProviderInterface
{

    /**
    * @var string[]
    */
    protected $methodCode = \PMNTS\Gateway\Model\Payment::CODE;
    protected $method;
    protected $currentCustomer;



    /**
     * @param \Magento\Payment\Helper\Data $paymentHelper
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        CurrentCustomer $currentCustomer
    ) {
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
        $this->currentCustomer = $currentCustomer;
    }


    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'pmntsGateway' => [
                    'iframeSrc' => $this->getIframeSrc(),
                    'isIframeEnabled' => $this->getIframeEnabled(),
                    'fraudFingerprintSrc' => $this->getFraudFingerprintSource(),
                    'isSandbox' => $this->getIsSandbox(),
                    'canSaveCard' => $this->canSaveCard(),
                    'customerHasSavedCC' => $this->customerHasSavedCC()
                ]
            ]
        ];
        return $config;
    }

    private function getConfigValue($key) {
      return $this->method->getConfigData($key);
    }

    private function getIframeEnabled() {
      return (bool)$this->getConfigValue("iframe_tokenization");
    }

    private function getFraudFingerprintSource() {
      $is_sandbox = $this->getConfigValue("sandbox_mode");
      $username = $this->getConfigValue("username");
      if ($is_sandbox) {
          return "https://gateway.pmnts-sandbox.io/fraud/fingerprint/{$username}.js";
      } else {
        return "https://gateway.pmnts.io/fraud/fingerprint/{$username}.js";
      }
    }

    private function getIframeSrc() {
        $is_sandbox = $this->getConfigValue("sandbox_mode");
        $username = $this->getConfigValue("username");
        $shared_secret = $this->getConfigValue("shared_secret");
        $nonce = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
        $hash_payload = "{$nonce}:1.0:AUD";
        $hash = hash_hmac ("md5", $hash_payload, $shared_secret);

        $base_url = "https://paynow.pmnts.io";
        if($is_sandbox) {
            $base_url = "https://paynow.pmnts-sandbox.io";
        }

        $url = "{$base_url}/v2/{$username}/{$nonce}/AUD/1.0/{$hash}?show_extras=false&show_email=false&iframe=true&paypal=false&tokenize_only=true&masterpass=false&visacheckout=false&hide_button=true&postmessage=true&return_target=_self&ajax=true";

        // If CSS URL is set, generate signature, add to iframe URL
        $css_url = $this->getConfigValue("iframe_css");
        if (strlen($css_url) > 0) {
          $css_signature = hash_hmac("md5", $css_url, $shared_secret);
          $url = $url . "&css={$css_url}&css_signature={$css_signature}";
        }

        return $url;
    }

    private function getIsSandbox() {
      $is_sandbox = $this->getConfigValue("sandbox_mode");

      return $is_sandbox;
    }

    private function canSaveCard() {
      $customer = $this->currentCustomer->getCustomerId();
      return !is_null($customer) && $this->getConfigValue('customer_save_credit_card');
    }

    private function customerHasSavedCC() {
      $customer_id = $this->currentCustomer->getCustomerId();
      if (!isset($customer_id)) { return false;}
       $customer = $this->currentCustomer->getCustomer();
       if (is_null($customer)) {
         return false;
       } else {
         $attrs = $customer->getCustomAttributes();
         return isset($attrs['pmnts_card_token']);
       }
    }

}
