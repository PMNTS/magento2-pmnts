<?php
namespace PMNTS\Gateway\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const METHOD_CODE = 'pmnts_gateway';
    const VAULT_METHOD_CODE = 'pmnts_gateway_vault';

    const CONFIG_PATH_PMNTS_USERNAME = 'payment/pmnts_gateway/username';
    const CONFIG_PATH_PMNTS_TOKEN = 'payment/pmnts_gateway/token';
    const CONFIG_PATH_PMNTS_SANDBOX = 'payment/pmnts_gateway/sandbox_mode';


    const RE_ANS = "/[^A-Z\d\-_',\.;:\s]*/i";
    const RE_AN = "/[^A-Z\d]/i";
    const RE_NUMBER = "/[^\d]/";

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;
    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->scopeConfig = $context->getScopeConfig();
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getOrderReference($order)
    {
        $merchantReference = $order->getIncrementId();
        $prefix = $this->getConfigData('reference_prefix', $order->getStoreId());
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
        } catch (\Magento\Framework\Exception\NoSuchEntityException $ex) {
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
                    "address_1" => $this->cleanForFraud($billing->getStreetLine(1) . ' ' . $billing->getStreetLine(2), self::RE_ANS, 30),
                    "city" => $this->cleanForFraud($billing->getCity(), self::RE_ANS, 20),
                    "country" => \FatZebra\Helpers::iso3166_alpha3($billing->getCountryId()),
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
                    "address_1" => $this->cleanForFraud($billing->getStreetLine(1) . ' ' . $billing->getStreetLine(2), self::RE_ANS, 30),
                    "city" => $this->cleanForFraud($billing->getCity(), self::RE_ANS, 20),
                    "country" => \FatZebra\Helpers::iso3166_alpha3($billing->getCountryId()),
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

        if (!is_null($shipping)) {
            $fraudData["shipping_address"] = [
                "address_1" => $this->cleanForFraud($shipping->getStreetLine(1) . ' ' . $shipping->getStreetLine(2), self::RE_ANS, 30),
                "city" => $this->cleanForFraud($shipping->getCity(), self::RE_ANS, 20),
                "country" => \FatZebra\Helpers::iso3166_alpha3($billing->getCountryId()),
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

        $methodLowcost = explode(',', $this->getConfigData('fraud_ship_lowcost', $order->getStoreId()));
        $methodOvernight = explode(',', $this->getConfigData('fraud_ship_overnight', $order->getStoreId()));
        $methodSameday = explode(',', $this->getConfigData('fraud_ship_sameday', $order->getStoreId()));
        $methodPickup = explode(',', $this->getConfigData('fraud_ship_pickup', $order->getStoreId()));
        $methodExpress = explode(',', $this->getConfigData('fraud_ship_express', $order->getStoreId()));
        $methodInternational = explode(',', $this->getConfigData('fraud_ship_international', $order->getStoreId()));

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

    protected function getConfigData($field, $storeId = 0)
    {
        return $this->scopeConfig->getValue('payment/pmnts_gateway/' . $field, 'stores', $storeId);
    }
}
