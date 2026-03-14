<?php
/**
 * TabaPay API client for create and verify transactions.
 *
 * @package Tabapay_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TabaPayAPI
 */
class TabaPayAPI {

	/**
	 * Create transaction endpoint URL.
	 *
	 * @var string
	 */
	private $createURL;

	/**
	 * Verify transaction endpoint URL.
	 *
	 * @var string
	 */
	private $verifyURL;

	/**
	 * Merchant key for authorization.
	 *
	 * @var string
	 */
	private $merchant;

	/**
	 * Constructor.
	 *
	 * @param string $merchant Merchant key.
	 * @param string $api_base_url Base URL for API (e.g. https://api.tabapay.ir/v1 or https://api.tabapay.ir/v1/sandbox).
	 */
	public function __construct( $merchant, $api_base_url = 'https://api.tabapay.ir/v1' ) {
		$this->createURL = trailingslashit( $api_base_url ) . 'create';
		$this->verifyURL = trailingslashit( $api_base_url ) . 'verify';
		$this->merchant  = $merchant;
	}

	/**
	 * Creates a payment transaction.
	 *
	 * @param array $data Transaction data (amount, callbackURL, mobile, email, name, and optional invoice fields).
	 * @return array Response from API.
	 */
	public function CreateTransaction( $data ) {

		$body = array(
			'amount'           => isset( $data['amount'] ) ? $data['amount'] : 0,
			'callbackURL'      => !empty( $data['callbackURL'] ) ? $data['callbackURL'] : null,
			'mobile'           => !empty( $data['mobile'] ) ? $data['mobile'] : null,
			'email'            => !empty( $data['email'] ) ? $data['email'] : null,
			'name'             => !empty( $data['name'] ) ? $data['name'] : null,
			'sms'              => !empty( $data['sms'] ) ? $data['sms'] : null,
			'cardNumber'       => !empty( $data['cardNumber'] ) ? $data['cardNumber'] : null,
			'nationalCode'     => !empty( $data['nationalCode'] ) ? $data['nationalCode'] : null,
			'description'      => !empty( $data['description'] ) ? $data['description'] : null,
			'additionalData'   => !empty( $data['additionalData'] ) ? $data['additionalData'] : null,
			'invoiceProductId' => !empty( $data['invoiceProductId'] ) ? $data['invoiceProductId'] : null,
            'economicCode'     => !empty( $data['economicCode'] ) ? $data['economicCode'] : null,
            'buyerType'        => !empty( $data['buyerType'] ) ? $data['buyerType'] : null,
            'address'          => !empty( $data['address'] ) ? $data['address'] : null,
        );

		// If mobile is missing, force sms to 0 regardless of admin setting.
		if ( empty( $body['mobile'] ) ) {
			$body['sms'] = 0;
		}

		$post_data = wp_json_encode( $body );
		return $this->SendRequest( 'post', $this->createURL, $post_data, $this->merchant );
	}

	/**
	 * Verifies a transaction.
	 *
	 * @param string $token  Transaction token.
	 * @param int    $amount Amount (in Rial).
	 * @return array Response from API.
	 */
	public function VerifyTransaction( $token, $amount ) {
		$data = array(
			'token'  => $token,
			'amount' => $amount,
		);
		$postData = wp_json_encode( $data );
		return $this->SendRequest( 'post', $this->verifyURL, $postData, $this->merchant );
	}

	/**
	 * Sends HTTP request to API.
	 *
	 * @param string $method   HTTP method.
	 * @param string $url      Request URL.
	 * @param string|null $postData JSON body.
	 * @param string|null $merchant Merchant key.
	 * @return array Decoded response.
	 */
	private function SendRequest( $method, $url, $postData = null, $merchant = null ) {
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $merchant,
				'Content-Type' => 'application/json',
			),
		);

		if ( 'post' === $method ) {
			$args['method'] = 'POST';
			$args['body']   = $postData;
		} else {
			$args['method'] = 'GET';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'error'   => true,
				'message' => $response->get_error_message(),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}
}
