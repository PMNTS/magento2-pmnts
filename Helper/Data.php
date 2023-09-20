<?php
namespace PMNTS\Gateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Exception\NoSuchEntityException;
use FatZebra\Helpers;

class Data extends AbstractHelper
{
    public const METHOD_CODE = 'pmnts_gateway';
    public const VAULT_METHOD_CODE = 'pmnts_gateway_vault';

    public const CONFIG_PATH_PMNTS_USERNAME = 'payment/pmnts_gateway/username';
    public const CONFIG_PATH_PMNTS_TOKEN = 'payment/pmnts_gateway/token';
    public const CONFIG_PATH_PMNTS_SANDBOX = 'payment/pmnts_gateway/sandbox_mode';

    public const RE_ANS = "/[^A-Z\d\-_',\.;:\s]*/i";
    public const RE_AN = "/[^A-Z\d]/i";
    public const RE_NUMBER = "/[^\d]/";

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Context $context
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->scopeConfig = $context->getScopeConfig();
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Get Order Reference
     *
     * @param Order $order
     * @return string
     */
    public function getOrderReference($order): string
    {
        $merchantReference = $order->getIncrementId();
        $prefix = $this->getConfigData('reference_prefix', $order->getStoreId());
        if ($prefix) {
            $merchantReference = $prefix . $merchantReference;
        }
        return $merchantReference;
    }

    /**
     * Build Fraud Payload
     *
     * @param order $order
     * @return array|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function buildFraudPayload($order): ?array
    {
        if (!$this->getConfigData('fraud_detection_enabled', $order->getStoreId())) {
            return null;
        }

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $billing = $order->getBillingAddress();
        /** @var \Magento\Sales\Model\Order\Address $shipping */
        $shipping = $order->getShippingAddress();
        /** @var int $customerId */
        $customerId = $order->getCustomerId();
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();

        $existingCustomer = false;
        if (!$order->getCustomerIsGuest()) {
            try {
                $customer = $this->customerRepository->getById($customerId);
                $customerCreatedAt = date('c', strtotime($customer->getCreatedAt()));
                $existingCustomer = true;

                if ($customer->getDob() != '') {
                    $customerDob = date('c', strtotime($customer->getDob()));
                } else {
                    $customerDob = '';
                }
            } catch (\Exception $ex) {
                $customerCreatedAt = '';
                $customerDob = '';
            }
        } else {
            $customerCreatedAt = '';
            $customerDob = '';
        }

        try {
            $baseUrl = $this->storeManager->getStore($order->getStoreId())->getBaseUrl();
        } catch (NoSuchEntityException $ex) {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        }

        $orderItems = [];

        /**
         * @var \Magento\Sales\Api\Data\OrderItemInterface $item
         */
        foreach ($order->getAllItems() as $item) {
            $itemName = $item->getName();
            $itemId = $item->getProductId();
            $itemSku = $item->getSku();
            $orderItems[] = [
                "cost" => (float)$item->getPrice(),
                "description" => $this->cleanForFraud($itemName, self::RE_ANS, 32),
                "line_total" => (float)$item->getRowTotalInclTax(),
                "product_code" => $this->cleanForFraud($itemId, self::RE_ANS, 12, 'left'),
                "qty" => (int)$item->getQtyOrdered(),
                "sku" => $this->cleanForFraud($itemSku, self::RE_ANS, 32, 'left')
            ];
        }

        $fraudData = [
            "customer" => [
                    "address_1" => $this->cleanForFraud($billing->getStreetLine(1) . ' Data.php' .
                        $billing->getStreetLine(2), self::RE_ANS, 30),
                    "city" => $this->cleanForFraud($billing->getCity(), self::RE_ANS, 20),
                    "country" => Helpers::iso3166Alpha3($billing->getCountryId()),
                    "created_at" => $customerCreatedAt,
                    "date_of_birth" => $customerDob,
                    "email" => $order->getCustomerEmail(),
                    "existing_customer" => $existingCustomer,
                    "first_name" => $this->cleanForFraud($billing->getFirstname(), self::RE_ANS, 30),
                    "last_name" => $this->cleanForFraud($billing->getLastname(), self::RE_ANS, 30),
                    "home_phone" => $this->cleanForFraud($billing->getTelephone(), self::RE_NUMBER, 19),
                    "id" => $this->cleanForFraud($customerId, self::RE_ANS, 16),
                    "post_code" => $this->cleanForFraud($billing->getPostcode(), self::RE_AN, 9)
                ],
            "device_id" => $payment->getAdditionalInformation('pmnts_device_id'),
            "items" => $orderItems,
            "recipients" => [
                [
                    "address_1" => $this->cleanForFraud($billing->getStreetLine(1) . ' Data.php' .
                        $billing->getStreetLine(2), self::RE_ANS, 30),
                    "city" => $this->cleanForFraud($billing->getCity(), self::RE_ANS, 20),
                    "country" => Helpers::iso3166Alpha3($billing->getCountryId()),
                    "email" => $billing->getEmail(),
                    "first_name" => $this->cleanForFraud($billing->getFirstname(), self::RE_ANS, 30),
                    "last_name" => $this->cleanForFraud($billing->getLastname(), self::RE_ANS, 30),
                    "phone_number" => $this->cleanForFraud($billing->getTelephone(), self::RE_NUMBER, 19),
                    "post_code" => $this->cleanForFraud($billing->getPostcode(), self::RE_AN, 9),
                    "state" => $this->stateMap($billing->getRegion())
                ]
            ],
            "website" => $baseUrl
        ];

        if ($shipping !== null) {
            $fraudData["shipping_address"] = [
                "address_1" => $this->cleanForFraud($shipping->getStreetLine(1) . ' Data.php' .
                    $shipping->getStreetLine(2), self::RE_ANS, 30),
                "city" => $this->cleanForFraud($shipping->getCity(), self::RE_ANS, 20),
                "country" => Helpers::iso3166Alpha3($billing->getCountryId()),
                "email" => $shipping->getEmail(),
                "first_name" => $this->cleanForFraud($shipping->getFirstname(), self::RE_ANS, 30),
                "last_name" => $this->cleanForFraud($shipping->getLastname(), self::RE_ANS, 30),
                "home_phone" => $this->cleanForFraud($shipping->getTelephone(), self::RE_NUMBER, 19),
                "post_code" => $this->cleanForFraud($shipping->getPostcode(), self::RE_AN, 9),
                "shipping_method" => $this->getFraudShippingMethod($order)
            ];
        }

        return $fraudData;
    }

    /**
     * Clean For Fraud
     *
     * @param array|string $data
     * @param array|string $pattern
     * @param int $maxlen
     * @param string $trimDirection
     * @return array|false|string|string[]|null
     */
    public function cleanForFraud($data, $pattern, $maxlen, $trimDirection = 'right')
    {
        $data = preg_replace($pattern, '', \FatZebra\Helpers::toASCII($data)); // phpcs:ignore
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

    /**
     * State Map, Maps AU States to the codes... otherwise return the state scrubbed for fraud....
     *
     * @param mixed $stateName
     * @return array|false|string|string[]|null
     */
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
     * Get Fraud Shipping Method
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return string
     */
    public function getFraudShippingMethod($order): string
    {
        $shipping = $order->getShippingMethod();

        $methodLowcost = explode(',', $this->getConfigData('fraud_ship_lowcost', $order->getStoreId()));
        $methodOvernight = explode(
            ',',
            $this->getConfigData(
                'fraud_ship_overnight',
                $order->getStoreId()
            )
        );
        $methodSameday = explode(',', $this->getConfigData('fraud_ship_sameday', $order->getStoreId()));
        $methodPickup = explode(',', $this->getConfigData('fraud_ship_pickup', $order->getStoreId()));
        $methodExpress = explode(',', $this->getConfigData('fraud_ship_express', $order->getStoreId()));
        $methodInternational = explode(
            ',',
            $this->getConfigData(
                'fraud_ship_international',
                $order->getStoreId()
            )
        );

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

    /**
     * Get Config Data
     *
     * @param string $field
     * @param string|int|null $storeId
     * @return mixed
     */
    protected function getConfigData($field, $storeId = 0)
    {
        return $this->scopeConfig->getValue('payment/pmnts_gateway/' . $field, 'stores', $storeId);
    }
}
