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
    *
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
namespace PMNTS\Gateway\Model;

use Zend\Http\Client\Adapter\Exception\TimeoutException;

/**
* The Fat Zebra Gateway class for interfacing with Fat Zebra
*/
class Gateway
{
    /**
    * The version of this library
    */
    public $version = "1.1.6";

    /**
    * The URL of the Fat Zebra gateway
    */
    public $url = "https://gateway.fatzebra.com.au";

    /**
    * The sandbox URL of the Fat Zebra gateway
    */
    public $sandbox_url = "https://gateway.sandbox.fatzebra.com.au";

    /**
    * The API version for the requests
    */
    public $api_version = "1.0";

    /**
    * The gateway username
    */
    public $username;

    /**
    * The gateway token
    */
    public $token;

    /**
    * Indicates if test mode should be used or not
    */
    public $test_mode = true; // This needs to be set to false for production use.

    /**
    * The connection timeout - the maximum processing time for Fat Zebra is 30 seconds,
    * however in the event of a timeout the transaction will be re-queried which could increase the
    * processing time up to 50 seconds. Currently this is, on average, below 10 seconds.
    */
    public $timeout = 50;

    /**
    * Creates a new instance of the Fat Zebra gateway object
    * @param string $username the username for the gateway
    * @param string $token the token for the gateway
    * @param boolean $test_mode indicates if the test mode should be used or not
    * @param string $gateway_url the URL for the Fat Zebra gateway
    */
    public function __construct($username, $token, $test_mode = true, $gateway_url = null)
    {
        if (is_null($username) || strlen($username) === 0) {
            throw new \InvalidArgumentException("Username is required");
        }
        $this->username = $username;

        if (is_null($token) || strlen($token) === 0) {
            throw new \InvalidArgumentException("Token is required");
        }
        $this->token = $token;

        $this->test_mode = $test_mode;

        if ($this->test_mode) {
            $this->url = $this->sandbox_url;
        }

        if (!is_null($gateway_url)) {
            $this->url = $gateway_url;
        }
    }

    /**
     * Performs a purchase against the FatZebra gateway with a tokenized credit card
     * @param string $token the card token
     * @param float $amount the purchase amount
     * @param string $reference the purchase reference
     * @param string $cvv the card verification value - optional but recommended
     * @param array|null $fraud_data
     * @return array
     * @throws \Exception
     */
    public function token_purchase($token, $amount, $reference, $cvv = null, $fraud_data = null)
    {
        $customer_ip = $this->get_customer_ip();

        if (function_exists('bcmul')) {
            $int_amount = intval(bcmul($amount, 100));
        } else {
            $multiplied = round($amount * 100);
            $int_amount = (int)$multiplied;
        }
        $payload = [
            "customer_ip" => $customer_ip,
            "card_token" => $token,
            "cvv" => $cvv,
            "amount" => $int_amount,
            "reference" => $reference
            ];

        if (!is_null($fraud_data)) {
            $payload['fraud'] = $fraud_data;
        }
        return $this->do_request("POST", "/purchases", $payload);
    }

    /**
     * Performs a refund against the FatZebra gateway
     * @param string $transaction_id the original transaction ID to be refunded
     * @param float $amount the amount to be refunded
     * @param string $reference the refund reference
     * @return array
     * @throws \Exception
     */
    public function refund($transaction_id, $amount, $reference)
    {
        if (is_null($transaction_id) || strlen($transaction_id) === 0) {
            throw new \InvalidArgumentException("Transaction ID is required");
        }
        if (is_null($amount) || strlen($amount) === 0) {
            throw new \InvalidArgumentException("Amount is required");
        }
        if (intval($amount) < 1) {
            throw new \InvalidArgumentException("Amount is invalid - must be a positive value");
        }
        if (is_null($reference) || strlen($reference) === 0) {
            throw new \InvalidArgumentException("Reference is required");
        }

        if (function_exists('bcmul')) {
            $int_amount = intval(bcmul($amount, 100));
        } else {
            $multiplied = round($amount * 100);
            $int_amount = (int)$multiplied;
        }

        $payload = [
            "transaction_id" => $transaction_id,
            "amount" => $int_amount,
            "reference" => $reference
            ];

        return $this->do_request("POST", "/refunds", $payload);
    }

    // TODO: auth/captures

    /************** Private functions ***************/

    /**
     * Performs the request against the Fat Zebra gateway
     * @param string $method the request method ("POST" or "GET")
     * @param string $uri the request URI (e.g. /purchases, /credit_cards etc)
     * @param array $payload the request payload (if a POST request)
     * @return array
     * @throws \Exception
     */
    private function do_request($method, $uri, $payload = null)
    {
        $curl = curl_init();
        if (is_null($this->api_version)) {
            $url = $this->url . $uri;
            curl_setopt($curl, CURLOPT_URL, $url);
        } else {
            $url = $this->url . "/v" . $this->api_version . $uri;
            curl_setopt($curl, CURLOPT_URL, $url);
        }

        $payload["test"] = $this->test_mode;

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->username . ":" . $this->token);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["User-agent: FatZebra Magento2 Library " . $this->version]);

        if ($method == "POST" || $method == "PUT") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-type: application/json", "User-agent: FatZebra PHP Library " . $this->version]);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        if ($method == "PUT") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSLVERSION, 6); // CURLOPT_SSLVERSION_TLSv1_2
        curl_setopt($curl, CURLOPT_CAINFO, dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ca-bundle.crt');
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

        $data = curl_exec($curl);

        if (curl_errno($curl) !== 0) {
            if (curl_errno($curl) == 28) {
                throw new TimeoutException("cURL Timeout: " . curl_error($curl));
            }
            throw new \Exception("cURL error " . curl_errno($curl) . ": " . curl_error($curl));
        }
        curl_close($curl);

        $response =  json_decode($data, true);
        if (is_null($response)) {
            $err = json_last_error();
            if ($err == JSON_ERROR_SYNTAX) {
                throw new \Exception("JSON Syntax error. JSON attempted to parse: " . $data);
            } elseif ($err == JSON_ERROR_UTF8) {
                throw new \Exception("JSON Data invalid - Malformed UTF-8 characters. Data: " . $data);
            } else {
                throw new \Exception("JSON parse failed. Unknown error. Data:" . $data);
            }
        }

        return $response;
    }

    /**
    * Fetches the customers 'real' IP address (i.e. pulls out the address from X-Forwarded-For if present)
    *
    * @return String the customers IP address
    */
    private function get_customer_ip()
    {
        $customer_ip = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded_ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $customer_ip = $forwarded_ips[0];
        }

        return $customer_ip;
    }
}
