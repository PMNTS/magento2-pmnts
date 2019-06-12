<?php
namespace PMNTS\Gateway\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    const METHOD_CODE = 'pmnts_gateway';
    const VAULT_METHOD_CODE = 'pmnts_gateway_vault';

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
}
