<?php
/*
Plugin Name: TabaPay Gateway
Description: تاباپی - پرداخت یار رسمی شاپرک
Plugin URI: https://tabapay.ir
Version: 1.4.0
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

    include_once TABAPAY_PLUGIN_DIR . 'includes/class-tabapay-api.php';
    if (file_exists(TABAPAY_PLUGIN_DIR . 'includes/class-tabapay-invoice-admin.php')) {
        include_once TABAPAY_PLUGIN_DIR . 'includes/class-tabapay-invoice-admin.php';
        if (is_admin()) {
            new Tabapay_Invoice_Admin();
        }
    }

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
                    'desc_tip' => true,
                ),
                'enable_sms' => array(
                    'title' => __('ارسال پیامک تأیید تراکنش', 'tabapay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('فعال‌سازی ارسال SMS برای مشتری', 'tabapay-gateway'),
                    'description' => __('ارسال پیامک تایید تراکنش برای مشتری که هزینه آن از حساب دیجیتال خدمات پذیرنده کسر می‌شود و در صورت عدم موجودی، پیامک ارسال نمی‌شود.', 'tabapay-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'sandbox' => array(
                    'title' => __('حالت سندباکس', 'tabapay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('فعال‌سازی حالت سندباکس', 'tabapay-gateway'),
                    'description' => __('در صورت فعال بودن، درخواست‌ها به آدرس سندباکس API ارسال می‌شوند.', 'tabapay-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'tax_invoice_section' => array(
                    'title' => __('فاکتور مالیاتی', 'tabapay-gateway'),
                    'type' => 'title',
                    'description' => __('تنظیمات صدور خودکار فاکتور مالیاتی و اتصال فیلدهای به فیلدهای مالیاتی.', 'tabapay-gateway'),
                ),
                'enable_automatic_tax_invoice' => array(
                    'title' => __('فاکتور مالیاتی خودکار', 'tabapay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('فعال‌سازی صدور خودکار فاکتور مالیاتی', 'tabapay-gateway'),
                    'description' => __('در صورت فعال بودن، داده‌های فاکتور به API تاباپی ارسال می‌شوند.', 'tabapay-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'invoice_field_national_code' => array(
                    'title' => __('کد ملی (nationalCode)', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('آیدی فیلد برای کد ملی / شناسه ملی (مثال: billing_national_code)', 'tabapay-gateway'),
                    'default' => 'billing_national_code',
                    'desc_tip' => true,
                ),
                'invoice_field_mobile' => array(
                    'title' => __('موبایل (mobile)', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('آیدی فیلد برای موبایل (مثال: billing_phone)', 'tabapay-gateway'),
                    'default' => 'billing_phone',
                    'desc_tip' => true,
                ),
                'invoice_field_name' => array(
                    'title' => __('نام و نام خانوادگی (name)', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('آیدی فیلد(های) با کاما برای نام (مثال: billing_first_name,billing_last_name)', 'tabapay-gateway'),
                    'default' => 'billing_first_name,billing_last_name',
                    'desc_tip' => true,
                ),
                'invoice_field_address' => array(
                    'title' => __('آدرس (address)', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('آیدی فیلد برای آدرس (مثال: billing_address_1)', 'tabapay-gateway'),
                    'default' => 'billing_state,billing_city,billing_address_1,billing_address_2',
                    'desc_tip' => true,
                ),
                'invoice_field_economic_code' => array(
                    'title' => __('شماره اقتصادی (economicCode)', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('آیدی فیلد برای شماره اقتصادی (مثال: billing_economic_code). در صورت خالی بودن از nationalCode+0001 استفاده می‌شود.', 'tabapay-gateway'),
                    'default' => 'billing_economic_code',
                    'desc_tip' => true,
                ),
                'invoice_field_buyer_type' => array(
                    'title' => __('نوع خریدار (buyerType)', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('آیدی فیلد برای نوع خریدار؛ مقادیر مجاز: Natural یا Legal (مثال: billing_buyer_type)', 'tabapay-gateway'),
                    'default' => 'billing_buyer_type',
                    'desc_tip' => true,
                ),
                'invoice_shipping_product_id' => array(
                    'title' => __('شناسه خدمت هزینه پست (تاباپی)', 'tabapay-gateway'),
                    'type' => 'text',
                    'description' => __('اگر هزینه پست در سفارش دارید، شناسه کالا/خدمت اختصاصی هزینه پست را در تاباپی اینجا وارد کنید. در غیر این صورت خالی بگذارید یا گزینهٔ تقسیم را فعال کنید.', 'tabapay-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'invoice_distribute_shipping' => array(
                    'title' => __('تقسیم هزینه پست روی محصولات', 'tabapay-gateway'),
                    'type' => 'checkbox',
                    'label' => __('هزینه پست به‌صورت عدد صحیح بین محصولات تقسیم شود (شناسه خدمت پست استفاده نمی‌شود)', 'tabapay-gateway'),
                    'description' => __('در صورت فعال بودن، هزینه پست به نسبت مبلغ هر گروه روی همان شناسه‌های کالا اضافه می‌شود و جمع دقیقاً برابر مبلغ پست خواهد بود.', 'tabapay-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
            );
        }

        /**
         * Returns the API base URL based on sandbox setting.
         *
         * @return string Base URL for TabaPay API (without trailing slash).
         */
        public function get_api_base_url()
        {
            $sandbox = $this->get_option('sandbox', 'no');
            if ('yes' === $sandbox) {
                return 'https://api.tabapay.ir/v1/sandbox';
            }
            return 'https://api.tabapay.ir/v1';
        }

        /**
         * Returns checkout field key mapping for invoice fields (tax field => checkout field key(s)).
         *
         * @return array Associative array of invoice_key => checkout field key or comma-separated keys.
         */
        public function get_invoice_checkout_mapping()
        {
            return array(
                'nationalCode' => $this->get_option('invoice_field_national_code', 'billing_national_code'),
                'mobile' => $this->get_option('invoice_field_mobile', 'billing_phone'),
                'name' => $this->get_option('invoice_field_name', 'billing_first_name,billing_last_name'),
                'address' => $this->get_option('invoice_field_address', 'billing_state,billing_city,billing_address_1,billing_address_2'),
                'economicCode' => $this->get_option('invoice_field_economic_code', 'billing_economic_code'),
                'buyerType' => $this->get_option('invoice_field_buyer_type', 'billing_buyer_type'),
            );
        }

        /**
         * Gets invoice data from order based on checkout field mapping.
         *
         * @param WC_Order $order Order object.
         * @return array Associative array nationalCode, mobile, name, address, economicCode, buyerType (with fallback for economicCode).
         */
        public function get_invoice_data_from_order($order)
        {
            $mapping = $this->get_invoice_checkout_mapping();
            $get_value = function ($keys) use ($order) {
                $keys = array_map('trim', explode(',', $keys));
                $parts = array();
                foreach ($keys as $key) {
                    if ('' === $key) {
                        continue;
                    }
                    $val = $order->get_meta('_' . $key);
                    if ('' === (string)$val && isset($_POST[$key])) {
                        $val = sanitize_text_field(wp_unslash($_POST[$key]));
                    }
                    $parts[] = $val;
                }
                return implode(' ', $parts);
            };
            $national_code = $get_value($mapping['nationalCode']);
            $economic_code = $get_value($mapping['economicCode']);
            if ('' === $economic_code && '' !== $national_code) {
                $economic_code = $national_code . '0001';
            }
            $buyer_type = $get_value($mapping['buyerType']);
            $buyer_type = is_string($buyer_type) ? trim($buyer_type) : $buyer_type;

            if ('' !== $buyer_type) {
                // Normalize buyerType to API-supported values; accept common Persian labels too.
                $normalized = $buyer_type;
                if (in_array($buyer_type, array('حقیقی', 'شخص حقیقی'), true)) {
                    $normalized = 'Natural';
                } elseif (in_array($buyer_type, array('حقوقی', 'شخص حقوقی'), true)) {
                    $normalized = 'Legal';
                }
                if (in_array($normalized, array('Natural', 'Legal'), true)) {
                    $buyer_type = $normalized;
                }
            }

            return array(
                'nationalCode' => $national_code ?: null,
                'mobile' => $get_value($mapping['mobile']) ?: null,
                'name' => $get_value($mapping['name']) ?: null,
                'address' => $get_value($mapping['address']) ?: null,
                'economicCode' => $economic_code ?: null,
                'buyerType' => $buyer_type ?: null,
            );
        }

        /**
         * Builds invoiceProductId value for API: object mapping invoice product ID to total amount (per group).
         * All amounts are converted to Rial for TabaPay API using the same factor as the payment amount.
         *
         * @param WC_Order $order Order object.
         * @param string $currency Order currency.
         * @return array|null Associative array e.g. array( '1234' => '14325', '5678' => '98500' ) or null if no mapping.
         */
        public function get_invoice_product_id_for_order($order, $currency = '')
        {
            if (!class_exists('Tabapay_Invoice_Admin')) {
                return null;
            }
            $product_to_invoice = Tabapay_Invoice_Admin::get_product_to_invoice_id_map();
            if (empty($product_to_invoice)) {
                return null;
            }
            $currency = $currency ?: $order->get_currency();
            // Convert order currency to Rial for TabaPay API (same as process_payment amount).
            $factor = 1;
            if (strtolower($currency) === 'irt') {
                $factor = 10;
            } elseif (strtolower($currency) === 'irht') {
                $factor = 1000;
            } elseif (strtolower($currency) === 'irhr') {
                $factor = 100;
            }
            $group_totals      = array();
            $distinct_products = array(); // Distinct product_id (order preserved) for equal shipping split.
            foreach ( $order->get_items() as $item ) {
                if ( ! $item instanceof WC_Order_Item_Product ) {
                    continue;
                }
                $line_total = (float) $item->get_total();
                $amount     = (int) round( $line_total * $factor );
                $product_id = $item->get_product_id();

                if ( ! in_array( $product_id, $distinct_products, true ) ) {
                    $distinct_products[] = $product_id;
                }

                $invoice_id = isset( $product_to_invoice[ $product_id ] ) ? $product_to_invoice[ $product_id ] : null;
                if ( null === $invoice_id ) {
                    continue;
                }
                if ( ! isset( $group_totals[ $invoice_id ] ) ) {
                    $group_totals[ $invoice_id ] = 0;
                }
                $group_totals[ $invoice_id ] += $amount;
            }
            if ( empty( $group_totals ) ) {
                return null;
            }

            // Shipping in order currency; convert to Rial with same factor as products.
            $shipping_total_raw = (float) $order->get_shipping_total();
            $shipping_total_int = (int) round( $shipping_total_raw * $factor );
            $distribute         = ( 'yes' === $this->get_option( 'invoice_distribute_shipping', 'no' ) );
            $shipping_inv_id    = trim( (string) $this->get_option( 'invoice_shipping_product_id', '' ) );

            if ( $shipping_total_int > 0 ) {
                if ( $distribute && ! empty( $distinct_products ) ) {
                    // Equal split by number of distinct products; integer split so sum is exactly shipping.
                    $num   = count( $distinct_products );
                    $base  = (int) floor( $shipping_total_int / $num );
                    $rem   = $shipping_total_int - $num * $base;
                    $per_product = array();
                    foreach ( $distinct_products as $i => $pid ) {
                        $per_product[ $pid ] = $base + ( $i < $rem ? 1 : 0 );
                    }
                    foreach ( $distinct_products as $pid ) {
                        $inv_id = isset( $product_to_invoice[ $pid ] ) ? $product_to_invoice[ $pid ] : null;
                        if ( null !== $inv_id && isset( $per_product[ $pid ] ) ) {
                            $group_totals[ $inv_id ] += $per_product[ $pid ];
                        }
                    }
                } elseif ('' !== $shipping_inv_id) {
                    $existing = isset($group_totals[$shipping_inv_id]) ? (int)$group_totals[$shipping_inv_id] : 0;
                    $group_totals[$shipping_inv_id] = $existing + $shipping_total_int;
                }
            }

            $out = array();
            foreach ($group_totals as $inv_id => $total) {
                $out[(string)$inv_id] = (string)(int)$total;
            }
            return $out;
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
                'sms' => ('yes' === $this->get_option('enable_sms', 'no')) ? 1 : 0,
                'description' => sprintf(
                    __('Payment for Order #%1$s', 'tabapay-gateway'),
                    $order_id
                ),
                'additionalData' => wp_json_encode(array('order_id' => $order_id)),
            );

            if ('yes' === $this->get_option('enable_automatic_tax_invoice', 'no')) {
                $invoice_data = $this->get_invoice_data_from_order($order);
                $args = array_merge($args, array_filter($invoice_data));
                $invoice_prod = $this->get_invoice_product_id_for_order($order, $currency);
                if (null !== $invoice_prod) {
                    $args['invoiceProductId'] = $invoice_prod;
                }
            }

            $tabaPayAPI = new TabaPayAPI($this->merchant_key, $this->get_api_base_url());
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
                                );
                                $Notice = apply_filters('Tabapay_Success_Notice', $Notice, $order_id, $Transaction_ID);
                                if ($Notice)
                                    wc_add_notice($Notice, 'success');

                                do_action('Tabapay_Success', $order_id, $Transaction_ID);
                            } else
                                echo false;

                            exit;
                        }
                    } elseif (!empty($_GET['status']) && $_GET['status'] == "success" && $_GET['responseCode'] == 1) {
                        $token = sanitize_text_field($_GET['token']);
                        $tabaPayAPI = new TabaPayAPI($this->merchant_key, $this->get_api_base_url());

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