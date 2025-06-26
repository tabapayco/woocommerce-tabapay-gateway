<?php
/*
Plugin Name: TabaPay Gateway
Description: تاباپی - پرداخت یار رسمی شاپرک
Plugin URI: https://tabapay.ir
Version: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

define('TABAPAY_PLUGIN_FILE', __FILE__);
define('TABAPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TABAPAY_PLUGIN_URL', plugin_dir_url(__FILE__));


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'tabapay_gateway', 0);

function tabapay_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once 'TabaPay.php';

    class Tabapay_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'Tabapay_Gateway';
            $this->method_title = 'تاباپی';
            $this->method_description = 'تاباپی - پرداخت یار رسمی شاپرک - افزونه درگاه پرداخت برای ووکامرس';
            $this->has_fields = false;
            $this->icon = apply_filters('Tabapay_logo', TABAPAY_PLUGIN_URL . 'assets/images/logo.png');

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
                    'title' => __('فعال/غیرفعال', 'tabapay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('فعالسازی درگاه تاباپی', 'tabapay-gateway'),
                    'description' => __('برای فعالسازی درگاه پرداخت تاباپی باید چک باکس را تیک بزنید', 'tabapay-gateway'),
                    'default' => 'yes',
                    'desc_tip' => true
                ),
                'title' => array(
                    'title' => __('عنوان درگاه', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده می شود', 'tabapay-gateway'),
                    'default' => __('پرداخت امن با تاباپی', 'tabapay-gateway'),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __('توضیحات درگاه', 'tabapay-gateway'),
                    'type' => 'textarea',
                    'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'tabapay-gateway'),
                    'default' => __('درگاه پرداخت تاباپی، پرداخت‌یار رسمی شاپرک', 'tabapay-gateway'),
                    'desc_tip' => true
                ),
                'merchant_key' => array(
                    'title' => __('مرچنت کد', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('مرچنت کد درگاه تاباپی', 'tabapay-gateway'),
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
                'callbackURL' => add_query_arg('wc_order', $order_id, WC()->api_request_url('Tabapay_Gateway')),
                'mobile' => $order->get_billing_phone(),
                'email' => $order->get_billing_email(),
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'description' => sprintf(
                    __('Payment for Order #%1$s', 'tabapay-gateway'),
                    $order_id
                ),
                'additionalData' => json_encode(['order_id' => $order_id])
            );

            $tabaPayAPI = new TabaPayAPI($this->merchant_key);
            $responseData = $tabaPayAPI->CreateTransaction($args);

            if (!empty($responseData) && $responseData['status'] == "success" && !empty($responseData['url'])) {
                $order->update_status('pending', __('در انتظار پرداخت با درگاه تاباپی', 'tabapay-gateway'));
                return array(
                    'result' => 'success',
                    'redirect' => $responseData['url']
                );
            } else {
                wc_add_notice(__('خطا در پردازش تراکنش. لطفا مجددا تلاش کنید.', 'tabapay-gateway'), 'error');
                return;
            }
        }

        public function handle_tabapay_callback()
        {
            if (!empty($_GET['token']) || !empty($_POST['token'])) {
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

                    if (!empty($_SERVER['HTTP_AUTHORIZE']) && md5($this->merchant_key) == sanitize_text_field($_SERVER['HTTP_AUTHORIZE'])) {
                        $responseData = array_map('sanitize_text_field', $_POST);

                        if (isset($responseData['status'], $responseData['responseCode']) && $responseData['status'] == "success" && $responseData['responseCode'] == 1) {
                            $Transaction_ID = sanitize_text_field($responseData['trackingCode']);
                            $cardNumber = sanitize_text_field($responseData['cardNumber']);
                            $ip = sanitize_text_field($responseData['ip']);
                            $date = sanitize_text_field($responseData['date']);

                            $payment = $order->payment_complete();
                            if ($payment) {
                                echo(wp_json_encode(['status' => 'success']));

                                $Note = sprintf(
                                    __('پرداخت موفقیت آمیز بود.
                                        <br/> کد پیگیری : %1$s
                                        <br/> شماره کارت : %2$s
                                        <br/> آی‌پی : %3$s
                                        <br/> تاریخ : %4$s
                                        <br/> نوع تایید : تاباپی', 'tabapay-gateway'),
                                    esc_html($Transaction_ID),
                                    esc_html($cardNumber),
                                    esc_html($ip),
                                    esc_html($date)
                                );
                                $Note = apply_filters('Tabapay_Success_Note', $Note, $order_id, $Transaction_ID);
                                if ($Note)
                                    $order->add_order_note($Note, 1);

                                $Notice = sprintf(
                                    __('پرداخت موفقیت آمیز بود.
                                        <br/> کد رهگیری : %1$s', 'tabapay-gateway'),
                                    esc_html($Transaction_ID)
                                );                                $Notice = apply_filters('Tabapay_Success_Notice', $Notice, $order_id, $Transaction_ID);
                                if ($Notice)
                                    wc_add_notice($Notice, 'success');

                                do_action('Tabapay_Success', $order_id, $Transaction_ID);
                            } else
                                echo false;

                            exit;
                        }
                    } elseif (!empty($_GET['status']) && $_GET['status'] == "success" && $_GET['responseCode'] == 1) {
                        $token = sanitize_text_field($_GET['token']);
                        $tabaPayAPI = new TabaPayAPI($this->merchant_key);

                        $maxAttempts = 3;
                        $attempt = 0;
                        $responseData = null;

                        while ($attempt < $maxAttempts && (empty($responseData['status']))) {
                            $responseData = $tabaPayAPI->VerifyTransaction($token, $amount);
                            $attempt++;
                        }

                        if ($responseData['status'] == "success" && $responseData['responseCode'] == 1) {
                            $Transaction_ID = sanitize_text_field($responseData['trackingCode']);
                            $cardNumber = sanitize_text_field($responseData['cardNumber']);
                            $ip = sanitize_text_field($responseData['ip']);
                            $date = sanitize_text_field($responseData['date']);

                            $order->payment_complete();

                            $Note = sprintf(
                                __('پرداخت موفقیت آمیز بود.
                                    <br/> کد پیگیری : %1$s
                                    <br/> شماره کارت : %2$s
                                    <br/> آی‌پی : %3$s
                                    <br/> تاریخ : %4$s
                                    <br/> نوع تایید : تاباپی', 'tabapay-gateway'),
                                esc_html($Transaction_ID),
                                esc_html($cardNumber),
                                esc_html($ip),
                                esc_html($date)
                            );
                            $Note = apply_filters('Tabapay_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = sprintf(
                                __('پرداخت موفقیت آمیز بود.
                                    <br/> کد رهگیری : %1$s', 'tabapay-gateway'),
                                esc_html($Transaction_ID)
                            );
                            $Notice = apply_filters('Tabapay_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('Tabapay_Success', $order_id, $Transaction_ID);
                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        } else {
                            $message = isset($responseData['message']) ? esc_html($responseData['message']) : '';
                            $trackingCode = isset($responseData['trackingCode']) ? esc_html($responseData['trackingCode']) : '';

                            $Note = sprintf(
                                __('خطا در هنگام بازگشت از بانک : %1$s (شماره پیگیری %2$s)', 'tabapay-gateway'),
                                $message,
                                $trackingCode
                            );
                            $Note = apply_filters('Tabapay_Failed_Note', $Note, $order_id, $trackingCode);
                            if ($Note) {
                                $order->add_order_note($Note, 1);
                            }

                            $Note = apply_filters('Tabapay_Failed_Notice', $Note, $order_id, $trackingCode);
                            if ($Note) {
                                wc_add_notice($Note, 'error');
                            }

                            do_action('Tabapay_Failed', $order_id, $trackingCode);
                            wp_redirect(wc_get_checkout_url());
                            exit;
                        }
                    } else {
                        $responseCode = isset($_GET['responseCode']) ? intval($_GET['responseCode']) : '';
                        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

                        $Note = sprintf(
                            __('خطا در هنگام بازگشت از بانک : (کد خطا %1$s) (توکن %2$s)', 'tabapay-gateway'),
                            $responseCode,
                            $token
                        );

                        $Note = apply_filters('Tabapay_Failed_Note', $Note, $order_id, $token);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Note = apply_filters('Tabapay_Failed_Notice', $Note, $order_id, $token);
                        if ($Note) {
                            wc_add_notice($Note, 'error');
                        }

                        do_action('Tabapay_Failed', $order_id, $token);
                        wp_redirect(wc_get_checkout_url());
                        exit;
                    }
                } else {
                    $responseCode = isset($_GET['responseCode']) ? intval($_GET['responseCode']) : '';
                    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

                    $Note = sprintf(
                        __('خطا در هنگام بازگشت از بانک : (کد خطا %1$s) (توکن %2$s)', 'tabapay-gateway'),
                        $responseCode,
                        $token
                    );
                    $Note = apply_filters('Tabapay_Failed_Note', $Note, $order_id, $token);
                    if ($Note) {
                        $order->add_order_note($Note, 1);
                    }

                    $Note = apply_filters('Tabapay_Failed_Notice', $Note, $order_id, $token);
                    if ($Note) {
                        wc_add_notice($Note, 'error');
                    }

                    do_action('Tabapay_Failed', $order_id, $token);
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            } else {
                $Notice = __('شماره سفارش وجود ندارد .', 'tabapay-gateway');
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
        $methods[] = 'Tabapay_Gateway';
        return $methods;
    }
}