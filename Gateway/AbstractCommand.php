<?php

namespace PMNTS\Gateway\Gateway;

abstract class AbstractCommand implements \Magento\Payment\Gateway\CommandInterface
{
    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;

    /** @var \PMNTS\Gateway\Helper\Data */
    protected $pmntsHelper;

    /** @var \PMNTS\Gateway\Model\GatewayFactory */
    protected $gatewayFactory;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /**
     * AbstractCommand constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \PMNTS\Gateway\Helper\Data $pmntsHelper
     * @param \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PMNTS\Gateway\Helper\Data $pmntsHelper,
        \PMNTS\Gateway\Model\GatewayFactory $gatewayFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->pmntsHelper = $pmntsHelper;
        $this->gatewayFactory = $gatewayFactory;
        $this->logger = $logger;
    }

    /**
     * @param $storeId
     * @return \Pmnts\Gateway\Model\Gateway
     */
    public function getGateway($storeId)
    {
        $username = $this->scopeConfig->getValue('payment/pmnts_gateway/username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $token = $this->scopeConfig->getValue('payment/pmnts_gateway/token', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $sandbox = (bool)$this->scopeConfig->getValue('payment/pmnts_gateway/sandbox_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);

        return $this->gatewayFactory->create([
            'username' => $username,
            'token' => $token,
            'test_mode' => $sandbox
        ]);
    }
}