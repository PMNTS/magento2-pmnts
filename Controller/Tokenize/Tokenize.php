<?php
namespace MindArc\FatZebra\Controller\Tokenize;

class Tokenize extends \Magento\Framework\App\Action\Action
{
    public $api_version = "2";
    public $url = "https://gateway.sandbox.fatzebra.com.au";
    public $_username;
    public $_token;
    public $helper;
    public $test_mode;

    public function execute()
    {
        $object_manager     = \Magento\Framework\App\ObjectManager::getInstance();
        $helper             = $object_manager->get('MindArc\FatZebra\Helper\Data');

        $this->_username    = $helper->getConfig('payment/fatzebra/fatzebra_username');
        $this->_token       = $helper->getConfig('payment/fatzebra/fatzebra_token');
        $this->test_mode    = $helper->getConfig('payment/fatzebra/fatzebra_test_mode');
        $shared_secret      = $helper->getConfig('payment/fatzebra/fatzebra_shared_secret');
        $nonce              = uniqid("fzdirect-");
        $verification       = hash_hmac('md5', $nonce, $shared_secret);

        if (isset($_POST['cc_holder']) && isset($_POST['cc_number']) && isset($_POST['cc_month']) && isset($_POST['cc_year']) && isset($_POST['cc_vid'])) {
            $payload = array(
                'format' => 'json',
                'card_holder' => $_POST['cc_holder'],
                'card_number' => $_POST['cc_number'],
                'expiry_month' => $_POST['cc_month'],
                'expiry_year' => $_POST['cc_year'],
                'cvv' => $_POST['cc_vid'],
                'return_path' => $nonce,
                'verification' => $verification
            );
            $result = $this->do_request("POST", '/credit_cards/direct/' . $this->_username, $payload);

//        echo 'Credit Card Tokenize Result: <pre>' . print_r($result, true) . '</pre></br>';

            if ($result->r != 97) {
                $tokenData['status'] = 'SUCCESS';
                $tokenData['cc_token'] = $result->token;
                $tokenData['cc_number'] = $result->card_number;
                $tokenData['cc_owner'] = $result->card_holder;
            } else {
                $tokenData['status'] = 'ERROR';
                $tokenData['message'] = 'Could not tokenize data';
            }
        } else {
            $tokenData['status'] = 'ERROR';
            $tokenData['message'] = 'Could not tokenize data';
        }

        echo json_encode($tokenData);
    }

    private function do_request($method, $uri, $payload = null) {
        $curl = curl_init();
        if(is_null($this->api_version)) {
            $url = $this->url . $uri;
            curl_setopt($curl, CURLOPT_URL, $url);
        } else {
            $url = $this->url . "/v" . $this->api_version . $uri;
            curl_setopt($curl, CURLOPT_URL, $url);
        }

        $payload["test"] = $this->test_mode;

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $this->_username .":". $this->_token);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("User-agent: FatZebra PHP Library " . $this->api_version));

        if ($method == "POST" || $method == "PUT") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json", "User-agent: FatZebra PHP Library " . $this->api_version));
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        if ($method == "PUT") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSLVERSION, 6); // CURLOPT_SSLVERSION_TLSv1_2
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        $data = curl_exec($curl);

        if (curl_errno($curl) !== 0) {
            if (curl_errno($curl) == 28) throw new TimeoutException("cURL Timeout: " . curl_error($curl));
            throw new \Exception("cURL error " . curl_errno($curl) . ": " . curl_error($curl));
        }
        curl_close($curl);

        $response =  json_decode($data);
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
}
