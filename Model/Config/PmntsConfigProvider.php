<?php

namespace PMNTS\Gateway\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;

class PmntsConfigProvider implements ConfigProviderInterface
{

    /**
    * @var string[]
    */
    protected $methodCode = \PMNTS\Gateway\Model\Payment::CODE;
    /**
    * @var \Foggyline\Paybox\Model\Paybox
    */
    protected $method;

    /**
     * @param \Magento\Payment\Helper\Data $paymentHelper
     */
    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper
    ) {
        $this->method = $paymentHelper->getMethodInstance($this->methodCode);
    }


    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'pmntsGateway' => [
                    'iframeSrc' => $this->getIframeSrc()
                ]
            ]
        ];
        return $config;
    }

    private function getConfigValue($key) {
      return $this->method->getValue("payment/pmnts_gateway/$key");
    }

    private function getIframeEnabled() {
      return (bool)$this->getConfigValue("iframe_tokenization");
    }

    private function getIframeSrc() {
        $is_sandbox = $this->getConfigValue("sandbox_mode");
        $username = $this->getConfigValue("username");
        $shared_secret = $this->getConfigValue("shared_secret");
        $nonce = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
        $hash_payload = "$nonce:100.25:AUD";
        $hash = hash_hmac ("md5", $hash_payload, $shared_secret);

        $base_url = "https://paynow.pmnts.io";
        if($is_sandbox) {
            $base_url = "https://paynow-sandbox.pmnts.io";
        }

        $url = "{$base_url}/v2/{$username}/{$nonce}/AUD/1.0/{$hash}?show_extras=false&show_email=false&iframe=true&paypal=false&tokenize=true&masterpass=false&visacheckout=false&hide_button=true";

        // If CSS URL is set, generate signature, add to iframe URL
        $css_url = $this->getConfigValue("iframe_css");
        if (strlen($css_url) > 0) {
          $css_signature = hash_hmac("md5", $css_url, $shared_secret);
          $url = $url . "&css={$css_url}&css_signature={$css_signature}";
        }

        return $url;
    }

}
