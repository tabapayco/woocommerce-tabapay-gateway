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
		// Normalize optional tax invoice fields so that keys always exist (even when null).
		$economic_code = null;
		if ( array_key_exists( 'economicCode', $data ) && null !== $data['economicCode'] && '' !== $data['economicCode'] ) {
			$economic_code = (string) $data['economicCode'];
		}

		$buyer_type = null;
		if ( array_key_exists( 'buyerType', $data ) ) {
			$raw = $data['buyerType'];
			if ( null !== $raw && '' !== $raw ) {
				$normalized = strtolower( (string) $raw );
				if ( in_array( $normalized, array( 'real', 'legal' ), true ) ) {
					$buyer_type = $normalized;
				}
			}
		}

		$body = array(
			'amount'         => isset( $data['amount'] ) ? $data['amount'] : 0,
			'callbackURL'    => isset( $data['callbackURL'] ) ? $data['callbackURL'] : '',
			'mobile'         => ! empty( $data['mobile'] ) ? $data['mobile'] : null,
			'email'          => ! empty( $data['email'] ) ? $data['email'] : null,
			'name'           => ! empty( $data['name'] ) ? $data['name'] : null,
			'sms'            => ! empty( $data['sms'] ) ? $data['sms'] : null,
			'cardNumber'     => ! empty( $data['cardNumber'] ) ? $data['cardNumber'] : null,
			'nationalCode'   => ! empty( $data['nationalCode'] ) ? $data['nationalCode'] : null,
			'economicCode'   => $economic_code,
			'buyerType'      => $buyer_type,
			'description'    => ! empty( $data['description'] ) ? $data['description'] : null,
			'additionalData'  => ! empty( $data['additionalData'] ) ? $data['additionalData'] : null,
		);
		if ( ! empty( $data['address'] ) ) {
			$body['address'] = $data['address'];
		}

		// If mobile is missing, force sms to 0 regardless of admin setting.
		if ( empty( $body['mobile'] ) ) {
			$body['sms'] = 0;
		}
		if ( isset( $data['invoiceProductId'] ) ) {
			if ( is_array( $data['invoiceProductId'] ) && ! empty( $data['invoiceProductId'] ) ) {
				$body['invoiceProductId'] = $data['invoiceProductId'];
			} elseif ( is_string( $data['invoiceProductId'] ) && '' !== $data['invoiceProductId'] ) {
				$body['invoiceProductId'] = $data['invoiceProductId'];
			}
		}
		$post_data = wp_json_encode( $body );
		return $this->SendRequest( 'post', $this->createURL, $post_data, $this->merchant );
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