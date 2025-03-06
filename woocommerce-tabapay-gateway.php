<?php
/*
Plugin Name: TabaPay Gateway
Description: تاباپی - پرداخت یار رسمی شاپرک
Plugin URI: https://tabapay.ir
Version: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'init_tabapay_gateway',0);

function init_tabapay_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

	include_once 'TabaPay.php';

    class WC_Tabapay_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'WC_Tabapay_Gateway';
            $this->method_title = 'تاباپی';
            $this->method_description = 'تاباپی - پرداخت یار رسمی شاپرک - افزونه درگاه پرداخت برای ووکامرس';
            $this->has_fields = false;
            $this->icon = apply_filters('WC_Tabapay_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo.png');

            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->merchant_key = $this->get_option('merchant_key');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'handle_tabapay_callback'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('فعال/غیرفعال', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('فعالسازی درگاه تاباپی', 'woocommerce'),
                    'description' => __('برای فعالسازی درگاه پرداخت تاباپی باید چک باکس را تیک بزنید', 'woocommerce'),
                    'default' => 'yes',
                    'desc_tip' => true
                ),
                'title' => array(
                    'title' => __('عنوان درگاه', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده می شود', 'woocommerce'),
                    'default' => __('پرداخت امن با تاباپی', 'woocommerce'),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __('توضیحات درگاه', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                    'default' => __('درگاه پرداخت تاباپی، پرداخت‌یار رسمی شاپرک', 'woocommerce'),
                    'desc_tip' => true
                ),
                'merchant_key' => array(
                    'title' => __('مرچنت کد', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('مرچنت کد درگاه تاباپی', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true
                ),
            );
        }

        public function process_payment($order_id)
        {
			global $woocommerce;
            $woocommerce->order_id = $order_id;
            $order = wc_get_order($order_id);
            $currency = $order->get_currency();
            $amount = $order->get_total();

            if (strtolower($currency) === strtolower('IRT')) {
                $amount *= 10;
            } else if (strtolower($currency) === strtolower('IRHT')) {
                $amount *= 1000;
            } else if (strtolower($currency) === strtolower('IRHR')) {
                $amount *= 100;
            }

            $args = array(
                'amount' => $amount,
                'callbackURL' => add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Tabapay_Gateway')),
                'mobile' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'description' => sprintf(__('Payment for Order #%s', 'woocommerce'), $order_id),
                'additionalData' => json_encode(['order_id' => $order_id])
            );

            $tabaPayAPI = new TabaPayAPI($this->merchant_key);
            $responseData = $tabaPayAPI->CreateTransaction($args);

            if (!empty($responseData) && $responseData['status'] == "success" && !empty($responseData['url'])) {
                $order->update_status('pending', __('در انتظار پرداخت با درگاه تاباپی', 'woocommerce'));
                return array(
                    'result' => 'success',
                    'redirect' => $responseData['url']
                );
            } else {
                wc_add_notice(__('خطا در پردازش تراکنش. لطفا مجددا تلاش کنید.', 'woocommerce'), 'error');
                return;
            }
        }

        public function handle_tabapay_callback()
        {
            if (!empty($_GET['token'])) {
                if (isset($_GET['wc_order'])) {
                    $order_id = sanitize_text_field($_GET['wc_order']);
                } else {
					global $woocommerce;
                    $order_id = $woocommerce->order_id;
                    unset($woocommerce->order_id);
                }
				
                $order = wc_get_order($order_id);

				if ($order && !is_user_logged_in()) {
					$user_id = $order->get_user_id();
					if ($user_id) { // بررسی اینکه user_id معتبر است و سفارش توسط کاربر وارد شده
						wp_clear_auth_cookie();
						wp_set_current_user($user_id);
						wp_set_auth_cookie($user_id, true, false); 
					}
				}

                if ($order && $order->get_status() === 'pending') {
                    $currency = $order->get_currency();
                    $amount = $order->get_total();
                    //$amount = isset($_GET['amount']) ? wc_clean($_GET['amount']) : '';

                    if (strtolower($currency) === strtolower('IRT')) {
                        $amount *= 10;
                    } else if (strtolower($currency) === strtolower('IRHT')) {
                        $amount *= 1000;
                    } else if (strtolower($currency) === strtolower('IRHR')) {
                        $amount *= 100;
                    }

                    if (!empty($_GET['status']) && $_GET['status'] == "success" && $_GET['responseCode'] == 1) {
                        $token = $_GET['token'];
                        $tabaPayAPI = new TabaPayAPI($this->merchant_key);
                        $responseData = $tabaPayAPI->VerifyTransaction($token, $amount);
                        if ($responseData['status'] == "success" && $responseData['responseCode'] == 1) {
                            $Transaction_ID = $responseData['trackingCode'];
                            $cardNumber = $responseData['cardNumber'];
                            $ip = $responseData['ip'];
                            $date = $responseData['date'];
                            $order->payment_complete();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود.
                                                <br/> کد پیگیری : %s
                                                <br/> شماره کارت : %s
                                                <br/> آی‌پی : %s
                                                <br/> تاریخ : %s', 'woocommerce'), $Transaction_ID, $cardNumber, $ip, $date);
                            $Note = apply_filters('Tabapay_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Notice = apply_filters('Tabapay_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('Tabapay_Success', $order_id, $Transaction_ID);
                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        } else {
                            $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s (شماره پیگیری %s)', 'woocommerce'), $responseData['message'], $responseData['trackingCode']);
                            $Note = apply_filters('Tabapay_Failed_Note', $Note, $order_id, $responseData['trackingCode']);
                            if ($Note) {
                                $order->add_order_note($Note, 1);
                            }

                            $Note = apply_filters('Tabapay_Failed_Notice', $Note, $order_id, $responseData['trackingCode']);
                            if ($Note) {
                                wc_add_notice($Note, 'error');
                            }

                            do_action('Tabapay_Failed', $order_id, $responseData['trackingCode']);
                            wp_redirect(wc_get_checkout_url());
                            exit;
                        }
                    } else {
                    $Note = sprintf(__('خطا در هنگام بازگشت از بانک : (کد خطا %s) (توکن %s)', 'woocommerce'), $_GET['responseCode'], $_GET['token']);
                        $Note = apply_filters('Tabapay_Failed_Note', $Note, $order_id, $_GET['token']);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Note = apply_filters('Tabapay_Failed_Notice', $Note, $order_id, $_GET['token']);
                        if ($Note) {
                            wc_add_notice($Note, 'error');
                        }

                        do_action('Tabapay_Failed', $order_id, $_GET['token']);
                        wp_redirect(wc_get_checkout_url());
                        exit;
                    }
                } else {
                    $Note = sprintf(__('خطا در هنگام بازگشت از بانک : (کد خطا %s) (توکن %s)', 'woocommerce'), $_GET['responseCode'], $_GET['token']);
                    $Note = apply_filters('Tabapay_Failed_Note', $Note, $order_id, $_GET['token']);
                    if ($Note) {
                        $order->add_order_note($Note, 1);
                    }

                    $Note = apply_filters('Tabapay_Failed_Notice', $Note, $order_id, $_GET['token']);
                    if ($Note) {
                        wc_add_notice($Note, 'error');
                    }

                    do_action('Tabapay_Failed', $order_id, $_GET['token']);
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            } else {
                $Notice = __('شماره سفارش وجود ندارد .', 'woocommerce');
                $Notice = apply_filters('Tabapay_Failed_Notice', $Notice);
                if ($Notice)
                    wc_add_notice($Notice, 'error');

                do_action('Tabapay_No_Order_ID', '0');
                wp_redirect(wc_get_checkout_url());
                exit;
            }
            wp_die('Invalid TabaPay callback');
        }
    }

    add_filter('woocommerce_payment_gateways', 'add_tabapay_gateway');
	
	function add_tabapay_gateway($methods)
    {
        $methods[] = 'WC_Tabapay_Gateway';
        return $methods;
    }
}
