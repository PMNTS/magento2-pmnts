<?php
/**
 * Gateway payment method model
 *
 * @category    PMNTS
 * @package     PMNTS_Gateway
 * @copyright   PMNTS (http://PMNTS.io)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PMNTS\Gateway\Model;
include('fatzebra.php');
use Psr\Log\LoggerInterface;
class Payment extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'pmnts_gateway';
    const RE_ANS = "/[^A-Z\d\-_',\.;:\s]*/i";
    const RE_AN = "/[^A-Z\d]/i";
    const RE_NUMBER = "/[^\d]/";

    protected $_code = self::CODE;
    protected $version = "1.0.1";

    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_GatewayApi = false;
    protected $_countryFactory;
    protected $_minAmount = null;
    protected $_maxAmount = null;

    protected $_storeManager;

    protected $_username;
    protected $_token;
    protected $_secret;
    protected $is_sandbox;
    protected $_referencePrefix = '';

    protected $_supportedCurrencyCodes = array();

    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_countryFactory = $countryFactory;
        $this->_logger_logger      = $logger;
        $this->_username    = $this->getConfigData('username');
        $this->_token       = $this->getConfigData('token');
        $this->_secret      = $this->getConfigData('shared_secret');
        $this->is_sandbox   = $this->getConfigData('sandbox_mode');
        $this->check_for_fraud   = $this->getConfigData('fraud_detection_enabled');
        $this->_referencePrefix   = $this->getConfigData('reference_prefix');
        $this->_storeManager = $storeManager;
        $this->_supportedCurrencyCodes = explode(',', $this->getConfigData('currencies'));

        $this->_GatewayApi = new \FatZebra\Gateway($this->_username, $this->_token, $this->is_sandbox);
        $this->_GatewayApi->version = $this->version;
    }

    /**
    * Capture payment method
    *
    * @param InfoInterface $payment
    * @param float $amount
    * @return $this
    * @throws \Magento\Framework\Exception\LocalizedException
    */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        $order      = $payment->getOrder();
        $billing    = $order->getBillingAddress();
        $shipping   = $order->getShippingAddress();
        $customerid = $order->getCustomerId();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $ccToken = $payment->getAdditionalInformation('pmnts_token');

        $requestData = [
            'amount'        => $amount,
            'currency'      => strtolower($order->getBaseCurrencyCode()),
            'description'   => sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
            'card'          => [
                'number'            => $payment->getCcNumber(),
                'exp_month'         => sprintf('%02d',$payment->getCcExpMonth()),
                'exp_year'          => $payment->getCcExpYear(),
                'cvc'               => $payment->getCcCid(),
                'name'              => $billing->getName(),
                'address_line1'     => $billing->getStreetLine(1),
                'address_line2'     => $billing->getStreetLine(2),
                'address_city'      => $billing->getCity(),
                'address_zip'       => $billing->getPostcode(),
                'address_state'     => $billing->getRegion(),
                'address_country'   => \FatZebra\Helpers::iso3166_alpha3($billing->getCountryId())
            ]
        ];

        if ($this->check_for_fraud) {
            if (!$order->getCustomerIsGuest()) {
                $existing_customer = true;
                $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($customerid);
                $customer_created_at = date('c', strtotime($customer->getCreatedAt()));

                if ($customer->getDob() != '') {
                    $customer_dob = date('c', strtotime($customer->getDob()));
                } else {
                    $customer_dob = '';
                }
            } else {
                $existing_customer = false;
                $customer_created_at = '';
                $customer_dob = '';
            }


            $ordered_items = $order->getAllItems();
            foreach ($ordered_items as $item) {
                $item_name = $item->getName();
                $item_id = $item->getProductId();
                $_newProduct = $item->getProduct();
                $item_sku = $_newProduct->getSku();
                $order_items[] = array("cost" => (float)$item->getPrice(),
                                       "description" => $this->cleanForFraud($item_name, self::RE_ANS, 26),
                                       "line_total" => (float)$item->getRowTotalInclTax(),
                                       "product_code" => $this->cleanForFraud($item_id, self::RE_ANS, 12, 'left'),
                                       "qty" => (int)$item->getQtyOrdered(),
                                       "sku" => $this->cleanForFraud($item_sku, self::RE_ANS, 12, 'left'));
            }

            $fraud_data = [
                "customer" =>
                    array(
                        "address_1" => $this->cleanForFraud($billing->getStreetLine(1) . ' ' . $billing->getStreetLine(2), self::RE_ANS, 30),
                        "city" => $this->cleanForFraud($billing->getCity(), self::RE_ANS, 20),
                        "country" => \FatZebra\Helpers::iso3166_alpha3($billing->getCountryId()),
                        "created_at" => $customer_created_at,
                        "date_of_birth" => $customer_dob,
                        "email" => $order->getCustomerEmail(),
                        "existing_customer" => $existing_customer,
                        "first_name" => $this->cleanForFraud($billing->getFirstname(), self::RE_ANS, 30),
                        "last_name" => $this->cleanForFraud($billing->getLastname(), self::RE_ANS, 30),
                        "home_phone" => $this->cleanForFraud($billing->getTelephone(), self::RE_NUMBER, 19),
                        "id" => $this->cleanForFraud($customerid, self::RE_ANS, 16),
                        "post_code" => $this->cleanForFraud($billing->getPostcode(), self::RE_AN, 9)
                    ),
                "device_id" => $payment->getAdditionalInformation('pmnts_device_id'),
                "items" => $order_items,
                "recipients" => array(
                    array("address_1" => $this->cleanForFraud($billing->getStreetLine(1) . ' ' . $billing->getStreetLine(2), self::RE_ANS, 30),
                          "city" => $this->cleanForFraud($billing->getCity(), self::RE_ANS, 20),
                          "country" => \FatZebra\Helpers::iso3166_alpha3($billing->getCountryId()),
                          "email" => $billing->getEmail(),
                          "first_name" => $this->cleanForFraud($billing->getFirstname(), self::RE_ANS, 30),
                          "last_name" => $this->cleanForFraud($billing->getLastname(), self::RE_ANS, 30),
                          "phone_number" => $this->cleanForFraud($billing->getTelephone(), self::RE_NUMBER, 19),
                          "post_code" => $this->cleanForFraud($billing->getPostcode(), self::RE_AN, 9),
                          "state" => $this->stateMap($billing->getRegion())
                    )
                ),
                "website" => $this->_storeManager->getStore()->getBaseUrl()
            ];

            if (!is_null($shipping)) {
                $fraud_data["shipping_address"] = array(
                    "address_1" => $this->cleanForFraud($shipping->getStreetLine(1) . ' ' . $shipping->getStreetLine(2), self::RE_ANS, 30),
                    "city" => $this->cleanForFraud($shipping->getCity(), self::RE_ANS, 20),
                    "country" => \FatZebra\Helpers::iso3166_alpha3($billing->getCountryId()),
                    "email" => $shipping->getEmail(),
                    "first_name" => $this->cleanForFraud($shipping->getFirstname(), self::RE_ANS, 30),
                    "last_name" => $this->cleanForFraud($shipping->getLastname(), self::RE_ANS, 30),
                    "home_phone" => $this->cleanForFraud($shipping->getTelephone(), self::RE_NUMBER, 19),
                    "post_code" => $this->cleanForFraud($shipping->getPostcode(), self::RE_AN, 9),
                    "shipping_method" => $this->getFraudShippingMethod($order)
                );
            }
        } else {
            $fraud_data = null;
        }

        if (isset($ccToken)) {
          $result = $this->_GatewayApi->token_purchase($ccToken,
                                                       $requestData['amount'],
                                                       $this->_referencePrefix . $order->getIncrementId(),
                                                       null,
                                                       $fraud_data);
        } else {
          $purchase_request = new \FatZebra\PurchaseRequest(
              $requestData['amount'],
              $this->_referencePrefix . $order->getIncrementId(),
              $billing->getName(),
              $payment->getCcNumber(),
              sprintf('%02d',$payment->getCcExpMonth()) ."/". $payment->getCcExpYear(),
              $payment->getCcCid(),
              $requestData['currency'],
              $fraud_data
          );

          $result = $this->_GatewayApi->purchase($purchase_request);
        }

        if ($result->successful && $result->response->successful) {
             $payment->setTransactionId($result->response->id)->setIsTransactionClosed(1);
             $this->saveFraudResponse($payment, $order, $result);

             if (!$order->getCustomerIsGuest() && $payment->getAdditionalInformation('pmnts_cc_save')) {
                 $existing_customer = true;
                 $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($customerid);
                 $customer->setCustomAttributes(array(
                   "pmnts_card_token" => $result->response->token,
                   "pmnts_card_number" => $result->response->card_number,
                   "pmnts_card_expiry" => $result->response->card_expiry
                 ));
               }
        } else if ($result->successful) {
            $this->saveFraudResponse($payment, $order, $result);
            throw new \Magento\Framework\Validator\Exception(__('Payment error - ' . $result->response->message));
        } else {
            throw new \Magento\Framework\Validator\Exception(__('Payment error - ' . implode(", ", $result->errors)));
        }

        return $this;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount) {
        $transactionId = $payment->getParentTransactionId();
        $order = $payment->getOrder();

        $requestData = [
            'transaction_id'    => $transactionId,
            'amount'            => $amount,
            'reference_id'      => $this->_referencePrefix . $order->getIncrementId() . '-' . time()
        ];

        $result = $this->_GatewayApi->refund($requestData['transaction_id'], $requestData['amount'], $requestData['reference_id']);
        if ($result->successful && $result->response->successful) {
          $payment
              ->setTransactionId($result->response->id)
              ->setParentTransactionId($transactionId)
              ->setIsTransactionClosed(1)
              ->setShouldCloseParentTransaction(1);
        } elseif ($result->successful) {
            throw new \Magento\Framework\Validator\Exception(__('Payment refunding error - ' . $result->response->message));
        } else {
            throw new \Magento\Framework\Validator\Exception(__('Payment refunding error - ' . implode(', ', $result->errors)));
        }

        return $this;
    }

    /**
     * Check for a negative fraud response and record the status if present
     *
     * @param \Magento\Payment\Model\InfoInterface $payment the payment InfoInterface
     * @param $order the Magento order
     * @param $result the payment gateway result
     */
    function saveFraudResponse(\Magento\Payment\Model\InfoInterface $payment, $order, $result) {
      if (!$this->check_for_fraud) return false;
      if (property_exists($result->response, 'fraud_result')) {
          $fraud_result = strtolower($result->response->fraud_result);
          if ($fraud_result != 'accept') {
          $payment->setIsFraudDetected($fraud_result == 'challenge' || $fraud_result == 'deny');
          $payment->setOrderStatePaymentReview("The following rules triggered a fraud review: " . implode(',', $result->response->fraud_messages), $result->response->id);
          $fraudMessage = "Fraud result: " . strtoupper($fraud_result) . ". The following rules triggered a fraud review: " . implode(',', $result->response->fraud_messages);
          $order->addStatusHistoryComment($fraudMessage);

          //set data of fraud
            $fraud_data = [];
            $fraud_data['Fraud Result'] = strtoupper($fraud_result);
            foreach($result->response->fraud_messages as $id => $msg) {
                $label = 'Fraud Message ' . ($id + 1);
                $fraud_data[$label] = $msg;
            }
            $payment->setTransactionAdditionalInfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
            $fraud_data
            );

          return true;
        }
      }

    	return false;
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
        return true;
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode) {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    // Maps AU States to the codes... otherwise return the state scrubbed for fraud....
    public function stateMap($stateName) {
        $states = array('Australia Capital Territory' => 'ACT',
                        'New South Wales' => 'NSW',
                        'Northern Territory' => 'NT',
                        'Queensland' => 'QLD',
                        'South Australia' => 'SA',
                        'Tasmania' => 'TAS',
                        'Victoria' => 'VIC',
                        'Western Australia' => 'WA');

        if (array_key_exists($stateName, $states)) {
            return $states[$stateName];
        } else {
            return $this->cleanForFraud($stateName, self::RE_AN, 10);
        }
    }

    public function getFraudShippingMethod($order) {
        $shipping = $order->getShippingMethod();

        $method_lowcost = explode(',', $this->getConfigData('fraud_ship_lowcost'));
        $method_overnight = explode(',', $this->getConfigData('fraud_ship_overnight'));
        $method_sameday = explode(',', $this->getConfigData('fraud_ship_sameday'));
        $method_pickup = explode(',', $this->getConfigData('fraud_ship_pickup'));
        $method_express = explode(',', $this->getConfigData('fraud_ship_express'));
        $method_international = explode(',', $this->getConfigData('fraud_ship_international'));

        if (in_array($shipping, $method_lowcost)) {
            return 'low_cost';
        }

        if (in_array($shipping, $method_overnight)) {
            return 'overnight';
        }

        if (in_array($shipping, $method_sameday)) {
            return 'same_day';
        }

        if (in_array($shipping, $method_pickup)) {
            return 'pickup';
        }

        if (in_array($shipping, $method_express)) {
            return 'express';
        }

        if (in_array($shipping, $method_international)) {
            return 'international';
        }

        return 'other';
    }

    public function cleanForFraud($data, $pattern, $maxlen, $trimDirection = 'right') {
        $data = preg_replace($pattern, '', $this->toASCII($data));
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

    /** Translates accented characters, ligatures etc to the latin equivalent.
     * @param $str string the input to be translated
     * @return string output once translated
     */
    public function toASCII($str) {
        return strtr(utf8_decode($str), utf8_decode(
            'ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ'), 'SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data) {
        if (!$data instanceof \Magento\Framework\DataObject) {
            $data = new \Magento\Framework\DataObject($data);
        }
        //var_dump($data->getData());
        $additionalData = $data->getData('additional_data');
        $data->setCcType($additionalData['cc_type']);
        $data->setCcExpMonth($additionalData['cc_exp_month']);
        $data->setCcExpYear($additionalData['cc_exp_year']);
        if (isset($additionalData['cc_number'])) {
          $data->setCcNumber($additionalData['cc_number']);
          $data->setCcLast4(substr($additionalData['cc_number'], -4));
        }
        if (isset($additionalData['cc_cid'])) $data->setCcCid($additionalData['cc_cid']);
        $info = $this->getInfoInstance();
        $info->setCcType(
            $data->getCcType()
        )->setCcOwner(
            $data->getCcOwner()
        )->setCcLast4(
            substr($data->getCcNumber(), -4)
        )->setCcNumber(
            $data->getCcNumber()
        )->setCcCid(
            $data->getCcCid()
        )->setCcExpMonth(
            $data->getCcExpMonth()
        )->setCcExpYear(
            $data->getCcExpYear()
        )->setCcSsIssue(
            $data->getCcSsIssue()
        )->setCcSsStartMonth(
            $data->getCcSsStartMonth()
        )->setCcSsStartYear(
            $data->getCcSsStartYear()
        );

        if (isset($additionalData['cc_token'])) $info->setAdditionalInformation('pmnts_token', $additionalData['cc_token']);
        if (isset($additionalData['io_bb'])) $info->setAdditionalInformation('pmnts_device_id', $additionalData['io_bb']);
        if (isset($additionalData['cc_save'])) $info->setAdditionalInformation('pmnts_save_cc', $additionalData['cc_save']);
        return $this;
    }

    // Override validate to skip if token used...
    public function validate() {
      $info = $this->getInfoInstance();
      $token = $info->getAdditionalInformation("pmnts_token");

      if (isset($token)) {
        return $this;
      }

      return parent::validate();
    }
}
