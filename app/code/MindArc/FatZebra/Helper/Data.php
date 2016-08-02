<?php
namespace MindArc\FatZebra\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Helper\Context $context
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue($config_path,  \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
