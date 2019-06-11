<?php

namespace PMNTS\Gateway\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
//TODO
include_once('/var/www/magento/app/code/PMNTS/Gateway/Model/fatzebra.php');

abstract class AbstractCommand implements \Magento\Payment\Gateway\CommandInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * AbstractCommand constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getGateway($storeId)
    {
        $username = $this->scopeConfig->getValue('payment/pmnts_gateway/username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $token = $this->scopeConfig->getValue('payment/pmnts_gateway/token', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $sandbox = (bool)$this->scopeConfig->getValue('payment/pmnts_gateway/sandbox_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);

        return new \FatZebra\Gateway($username, $token, $sandbox);
    }
}