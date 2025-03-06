<?php

class TabaPayAPI
{
    private $createURL;
    private $verifyURL;
    private $merchant;

    public function __construct($merchant)
    {
        $this->createURL = 'https://api.tabapay.ir/v1/create';
        $this->verifyURL = 'https://api.tabapay.ir/v1/verify';
        //SANDBOX
        /*
        $this->createURL = 'https://api.tabapay.ir/v1/sandbox/create';
        $this->verifyURL = 'https://api.tabapay.ir/v1/sandbox/verify';
        */
        $this->merchant = $merchant;
    }

    public function CreateTransaction($data)
    {
        // Request body
        $data = array(
            'amount' => $data['amount'],
            'callbackURL' => $data['callbackURL'],
            'mobile' => !empty($data['mobile']) ? $data['mobile'] : null,
            'email' => !empty($data['email']) ? $data['email'] : null,
            'name' => !empty($data['name']) ? $data['name'] : null,
            'sms' => !empty($data['sms']) ? $data['sms'] : null,
            'cardNumber' => !empty($data['cardNumber']) ? $data['cardNumber'] : null,
            'nationalCode' => !empty($data['nationalCode']) ? $data['nationalCode'] : null,
            'description' => !empty($data['description']) ? $data['description'] : null,
            'additionalData' => !empty($data['additionalData']) ? $data['additionalData'] : null
        );

        // Convert data to JSON format
        $postData = json_encode($data);

        // Send request and get response
        return $this->SendRequest("post", $this->createURL, $postData, $this->merchant);
    }

    public function VerifyTransaction($token, $amount)
    {
        // Request body
        $data = array(
            'token' => $token,
            'amount' => $amount
        );

        // Convert data to JSON format
        $postData = json_encode($data);

        // Send request and get response
        return $this->SendRequest("post", $this->verifyURL, $postData, $this->merchant);
    }


    private function SendRequest($method, $url, $postData = null, $merchant = null)
    {
        // Request headers
        $headers = array(
            'Authorization: Bearer ' . $merchant,
            'Content-Type: application/json',
        );

        // Initialize cURL session
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method == "get") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        }
        if ($method == "post") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        // Execute cURL session and get the response
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }

        // Close cURL session
        curl_close($ch);

        // Decode the API response
        return json_decode($response, 1);
    }
}

?>