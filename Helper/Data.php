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

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue($config_path,  \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
