<?php
namespace PMNTS\Gateway\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const METHOD_CODE = 'pmnts_gateway';
    const VAULT_METHOD_CODE = 'pmnts_gateway_vault';

    const RE_ANS = "/[^A-Z\d\-_',\.;:\s]*/i";
    const RE_AN = "/[^A-Z\d]/i";
    const RE_NUMBER = "/[^\d]/";

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context
    ) {
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getOrderReference($order)
    {
        $merchantReference = $order->getIncrementId();
        $prefix = $this->scopeConfig->getValue('payment/pmnts_gateway/reference_prefix', 'stores', $order->getStoreId());
        if ($prefix) {
            $merchantReference = $prefix . $merchantReference;
        }

        return $merchantReference;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array|null
     */
    public function buildFraudPayload($order)
    {
        if (!$this->scopeConfig->getValue('')) {
            return null;
        }
    }

    public function cleanForFraud($data, $pattern, $maxlen, $trimDirection = 'right')
    {
        $data = preg_replace($pattern, '', \FatZebra\Helpers::toASCII($data));
        $data = preg_replace('/[\r\n]/', ' ', $data);
        if (strlen($data) > $maxlen) {
            if ($trimDirection == 'right') {
                return substr($data, 0, $maxlen);
            } else {
                return substr($data, -1, $maxlen);
            }
        } else {
            return $data;
        }
    }

    // Maps AU States to the codes... otherwise return the state scrubbed for fraud....
    public function stateMap($stateName)
    {
        $states = [
            'Australia Capital Territory' => 'ACT',
            'New South Wales' => 'NSW',
            'Northern Territory' => 'NT',
            'Queensland' => 'QLD',
            'South Australia' => 'SA',
            'Tasmania' => 'TAS',
            'Victoria' => 'VIC',
            'Western Australia' => 'WA'
        ];

        if (array_key_exists($stateName, $states)) {
            return $states[$stateName];
        } else {
            return $this->cleanForFraud($stateName, self::RE_AN, 10);
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return string
     */
    public function getFraudShippingMethod($order)
    {
        $shipping = $order->getShippingMethod();

        $methodLowcost = explode(',', $this->getConfigData('fraud_ship_lowcost'));
        $methodOvernight = explode(',', $this->getConfigData('fraud_ship_overnight'));
        $methodSameday = explode(',', $this->getConfigData('fraud_ship_sameday'));
        $methodPickup = explode(',', $this->getConfigData('fraud_ship_pickup'));
        $methodExpress = explode(',', $this->getConfigData('fraud_ship_express'));
        $methodInternational = explode(',', $this->getConfigData('fraud_ship_international'));

        if (in_array($shipping, $methodLowcost)) {
            return 'low_cost';
        }

        if (in_array($shipping, $methodOvernight)) {
            return 'overnight';
        }

        if (in_array($shipping, $methodSameday)) {
            return 'same_day';
        }

        if (in_array($shipping, $methodPickup)) {
            return 'pickup';
        }

        if (in_array($shipping, $methodExpress)) {
            return 'express';
        }

        if (in_array($shipping, $methodInternational)) {
            return 'international';
        }

        return 'other';
    }
}
