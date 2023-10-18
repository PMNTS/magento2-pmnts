<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Payment\Gateway\CommandInterface;
use PMNTS\Gateway\Helper\Data;
use PMNTS\Gateway\Model\GatewayFactory;
use PMNTS\Gateway\Model\Gateway;
use Psr\Log\LoggerInterface;

abstract class AbstractCommand implements CommandInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Data
     */
    protected $pmntsHelper;

    /**
     * @var GatewayFactory
     */
    protected $gatewayFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * AbstractCommand constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $pmntsHelper
     * @param GatewayFactory $gatewayFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Data $pmntsHelper,
        GatewayFactory $gatewayFactory,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->pmntsHelper = $pmntsHelper;
        $this->gatewayFactory = $gatewayFactory;
        $this->logger = $logger;
    }

    /**
     * Get Gateway
     *
     * @param string|int|null $storeId
     * @return Gateway
     */
    public function getGateway($storeId): Gateway
    {
        $username = $this->scopeConfig->getValue(
            Data::CONFIG_PATH_PMNTS_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $token = $this->scopeConfig->getValue(
            Data::CONFIG_PATH_PMNTS_TOKEN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $sandbox = (bool)$this->scopeConfig->getValue(
            Data::CONFIG_PATH_PMNTS_SANDBOX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->gatewayFactory->create([
            'username' => $username,
            'token' => $token,
            'testMode' => $sandbox
        ]);
    }
}
