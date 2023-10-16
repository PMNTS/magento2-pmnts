<?php
/**
 * Adapted from Fat Zebra PHP Gateway Library
 * Version 1.1.5
 *
 * Created February 2012 - Matthew Savage (matthew.savage@fatzebra.com.au)
 * Updated 20 February 2012 - Matthew Savage (matthew.savage@fatzebra.com.au)
 * Updated 19 April 2012 - Matthew Savage (matthew.savage@fatzebra.com.au)
 *  - Added refund support
 *  - Added tokenization support
 * Updated 10 July 2012 - Matthew Savage (matthew.savage@fatzebra.com.au)
 *  - Added support for Plans, Customers and Subscriptions
 * Updated 01 July 2019 - Matthew O'Loughlin (matthew@aligent.com.au)
 *  - PSR pass and adapted for Magento 2
 *  - Stripped out unused functionality
 * Updated 06 April 2023 - Harsha Amaraweera (harsha.amaraweera@aligent.com.au)
 *  - PSR pass and adapted for Magento 246

 * The original source for this library, including its tests can be found at
 * https://github.com/fatzebra/PHP-Library
 *
 * Please visit http://docs.fatzebra.com.au for details on the Fat Zebra API
 * or https://www.fatzebra.com.au/help for support.
 *
 * Patches, pull requests, issues, comments and suggestions always welcome.
 *
 * @package FatZebra
 */
declare(strict_types=1);

namespace PMNTS\Gateway\Model;

use Zend\Http\Client\Adapter\Exception\TimeoutException;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The Fat Zebra Gateway class for interfacing with Fat Zebra
 */
class Gateway
{
    public const PMNTS_GOOGLE_PAYMENT_METHOD_CODE = 'googlepay';
    /**
     * @var string
     */
    public $version = "1.1.6";

    /**
     * @var mixed|string
     */
    public $url = "https://gateway.fatzebra.com.au";

    /**
     * @var string
     */
    public $sandbox_url = "https://gateway.sandbox.fatzebra.com.au";

    /**
     * @var string
     */
    public $api_version = "1.0";

    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $token;

    /**
     * The connection timeout - the maximum processing time for Fat Zebra is 30 seconds,
     * however in the event of a timeout the transaction will be re-queried which could increase the
     * processing time up to 50 seconds. Currently this is, on average, below 10 seconds.
     *
     * @var int
     */
    public int $timeout = 50;

    /**
     * @var State
     */
    private State $state;

    /**
     * @var bool
     */
    public bool $testMode = true;

    /**
     * @var Json
     */
    private Json $json;

    /**
     * Creates a new instance of the Fat Zebra gateway object
     *
     * @param string $username
     * @param string $token
     * @param State $state
     * @param Json $json
     * @param bool $testMode
     * @param string|null $gatewayUrl
     */
    public function __construct(
        string $username,
        string $token,
        State $state,
        Json $json,
        bool $testMode = true,
        string $gatewayUrl = null
    ) {
        if ($username ===null || strlen($username) === 0) {
            throw new \InvalidArgumentException("Username is required");
        }
        $this->username = $username;

        if ($token === null || strlen($token) === 0) {
            throw new \InvalidArgumentException("Token is required");
        }
        $this->token = $token;

        $this->testMode = $testMode;

        if ($this->testMode) {
            $this->url = $this->sandbox_url;
        }

        if ($gatewayUrl !== null) {
            $this->url = $gatewayUrl;
        }
        $this->state = $state;
        $this->json = $json;
    }

    /**
     * Performs a purchase against the FatZebra gateway with a tokenized credit card
     *
     * @param $pmntsToken
     * @param $currencyCode
     * @param $amount
     * @param $reference
     * @param $cvv
     * @param $fraud_data
     * @param $paymentType
     * @return mixed
     * @throws \Exception
     */
    public function tokenPurchase(
        $pmntsToken,
        $currencyCode,
        $amount,
        $reference,
        $cvv = null,
        $fraud_data = null,
        $paymentType = null
    ) {

        $payload = [];
        $customer_ip = $this->getCustomerIp();
        if (function_exists('bcmul')) {
            $int_amount = (int)bcmul((string)$amount, "100");
        } else {
            $multiplied = round($amount * 100);
            $int_amount = (int)$multiplied;
        }

        if ($paymentType == self::PMNTS_GOOGLE_PAYMENT_METHOD_CODE) {

            $payload = [
                "amount" => $int_amount,
                "currency" => $currencyCode,
                "reference" => $reference,
                "customer_ip" => $customer_ip,
                "wallet" => [
                    "type" => "GOOGLE",
                    "token" => $this->json->unserialize($pmntsToken)
                ]
            ];
        } else {
            $payload = [
                "customer_ip" => $customer_ip,
                "card_token" => $pmntsToken,
                "currency" => $currencyCode,
                "cvv" => $cvv,
                "amount" => $int_amount,
                "reference" => $reference
            ];
        }

        if ($fraud_data !== null) {
            $payload['fraud'] = $fraud_data;
        }
        return $this->doRequest("POST", "/purchases", $payload);
    }

    /**
     * Performs a refund against the FatZebra gateway
     *
     * @param string|int|null $transaction_id
     * @param string|float|int|null $amount
     * @param string|int|null $reference
     * @return mixed
     * @throws \Exception
     */
    public function refund($transaction_id, $amount, $reference)
    {
        if ($transaction_id === null || strlen($transaction_id) === 0) {
            throw new \InvalidArgumentException("Transaction ID is required");
        }
        if ($amount === null || strlen($amount) === 0) {
            throw new \InvalidArgumentException("Amount is required");
        }
        if ((int)$amount < 1) {
            throw new \InvalidArgumentException("Amount is invalid - must be a positive value");
        }
        if ($reference === null || strlen($reference) === 0) {
            throw new \InvalidArgumentException("Reference is required");
        }

        if (function_exists('bcmul')) {
            $int_amount = (int)bcmul((string)$amount, '100');
        } else {
            $multiplied = round($amount * 100);
            $int_amount = (int)$multiplied;
        }

        $payload = [
            "transaction_id" => $transaction_id,
            "amount" => $int_amount,
            "reference" => $reference
        ];

        return $this->doRequest("POST", "/refunds", $payload);
    }

    // TODO: auth/captures

    /**
     * Performs the request against the Fat Zebra gateway
     *
     * @param string $method
     * @param string $uri
     * @param mixed $payload
     * @return mixed
     * @throws \Exception
     */
    private function doRequest($method, $uri, $payload = null)
    {
        // phpcs:disable
        $curl = curl_init();
        if ($this->api_version === null) {
            $url = $this->url . $uri;
            curl_setopt($curl, CURLOPT_URL, $url);
        } else {
            $url = $this->url . "/v" . $this->api_version . $uri;
            curl_setopt($curl, CURLOPT_URL, $url);
        }

        $payload["test"] = $this->testMode;

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->username . ":" . $this->token);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["User-agent: FatZebra Magento2 Library " . $this->version]);

        if ($method == "POST" || $method == "PUT") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt(
                $curl,
                CURLOPT_HTTPHEADER,
                ["Content-type: application/json",
                    "User-agent: FatZebra PHP Library " . $this->version]
            );
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        if ($method == "PUT") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSLVERSION, 6); // CURLOPT_SSLVERSION_TLSv1_2
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

        $data = curl_exec($curl);

        if (curl_errno($curl) !== 0) {
            if (curl_errno($curl) == 28) {
                throw new TimeoutException("cURL Timeout: " . curl_error($curl));
            }
            throw new \Exception("cURL error " . curl_errno($curl) . ": " . curl_error($curl));
        }
        curl_close($curl);
        // phpcs:enable
        $response =  json_decode($data, true);
        if ($response === null) {
            $err = json_last_error();
            if ($err == JSON_ERROR_SYNTAX) {
                throw new \JsonException("JSON Syntax error. JSON attempted to parse: " . $data);
            } elseif ($err == JSON_ERROR_UTF8) {
                throw new \JsonException("JSON Data invalid - Malformed UTF-8 characters. Data: " . $data);
            } else {
                throw new \JsonException("JSON parse failed. Unknown error. Data:" . $data);
            }
        }

        return $response;
    }

    /**
     * Fetches the customers 'real' IP address (i.e. pulls out the address from X-Forwarded-For if present)
     *
     * @return String the customers IP address
     */
    private function getCustomerIp()
    {
        $customer_ip = $_SERVER['REMOTE_ADDR'];// phpcs:ignore
        if ($this->state->getAreaCode() == Area::AREA_CRONTAB) {
            $customer_ip = '127.0.0.1';
        } else {
            $customer_ip = $_SERVER['REMOTE_ADDR'];// phpcs:ignore
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {// phpcs:ignore
            $forwarded_ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);// phpcs:ignore
            $customer_ip = $forwarded_ips[0];
        }

        return $customer_ip;
    }
}
