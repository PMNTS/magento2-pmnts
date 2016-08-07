<?php

namespace PMNTS\Gateway\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;

class PmntsConfigProvider implements ConfigProviderInterface
{
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
      return $this->_scopeConfig->getValue("payment/pmnts_gateway/$key");
    }

    private function getIframeSrc() {
        $is_sandbox = $this->getConfigValue("iframe_tokenization");
        $username = $this->getConfigValue("username")
        $shared_secret = $this->getConfigValue("shared_secret");
        $nonce = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 5)), 0, 5);
        $hash_payload = "$nonce:100.25:AUD";
        $hash = hash_hmac ("md5", $hash_payload, $shared_secret);

        $url = "https://paynow.pmnts.io/v2/$username/$nonce/AUD/1.0/$hash?show_extras=false&iframe=false&paypal=false&show_extras=false";
        // https://paynow-sandbox.pmnts.io/
        if($is_sandbox) {
            $url = "https://paynow-sandbox.pmnts.io/v2/$username/$nonce/AUD/1.0/$hash?show_extras=false&iframe=false&paypal=false&show_extras=false";
        }

        // If CSS URL is set, generate signature, add to iframe URL
        if (strlen($css_url) > 0) {
          $css_url = $this->getConfigValue("iframe_css");
          $css_signature = hash_hmac("md5", $css_url, $shared_secret);
          $url = $url . "&css=$css_url&css_signature=$css_signature";
        }

        return $url;
    }

}
