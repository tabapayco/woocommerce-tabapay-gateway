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
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $merchant,
                'Content-Type' => 'application/json',
            )
        );

        if ($method === 'post') {
            $args['method'] = 'POST';
            $args['body'] = $postData;
        } else {
            $args['method'] = 'GET';
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'message' => $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

}