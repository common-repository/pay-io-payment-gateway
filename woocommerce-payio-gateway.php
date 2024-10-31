<?php

/**
 * Plugin Name:       Pay iO Payment Gateway
 * Description:       Pay instantly with your online bank app for a faster and more secure checkout. No cards or sign up required.
 * Version:           0.2.13
 * Author:            Pay iO Ltd
 * Author URI:        https://www.payio.co.uk
 */

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

// calls once on activation of plugins
add_action('plugins_loaded', 'payio_init_gateway_init');

// register ajax functions
add_action('wp_ajax_ec_ajax_response', ['WC_Payio_Gateway', 'ec_ajax_response']);
add_action('wp_ajax_nopriv_ec_ajax_response', ['WC_Payio_Gateway', 'ec_ajax_response']);

add_action('init', array('WC_Payio_Gateway', 'init_express_checkout'));

/**
 * payio_init_gateway_init
 *
 * @return void
 */
function payio_init_gateway_init()
{
    class WC_Payio_Gateway extends WC_Payment_Gateway
    {
        /**
         * includes_path
         *
         * @var string
         */

        public $includes_path;
        /**
         * api_path
         *
         * @var string
         */

        public $api_path;
        /**
         * id
         *
         * @var string
         */
        public $id;

        /**
         * button_label
         *
         * @var string
         */
        public $button_label;
        /**
         * __construct
         *
         * @return void
         */
        public function __construct($init_express_checkout = false)
        {
            $this->includes_path  = '/includes/';
            $this->api_path =  defined('PAYIO_API_PATH') ?  PAYIO_API_PATH : 'https://secure.payio.co.uk/api/';
            // set main plugin vars
            $this->id = 'payio_gateway'; // the unique ID for this gateway
            $this->button_label = $this->get_option('button_label');

            //express checkout needs to be included on everpage not just WC enabled pages, for this reason we need to include this func on each page load. 
            //init_express_checkout prevenbts duplication
            if (!$init_express_checkout) {
                include_once dirname(__FILE__) . $this->includes_path . 'class-wc-gateway-payio-settings.php';
                include_once dirname(__FILE__) . $this->includes_path . 'class-wc-gateway-payio-session-data.php';
                include_once dirname(__FILE__) . $this->includes_path . 'class-wc-gateway-payio-custom-order.php';
                include_once dirname(__FILE__) . $this->includes_path . 'class-wc-gateway-payio-webhooks.php';

                if ($this->button_label == 'payio-inline.png') {
                    $this->icon = esc_url_raw(apply_filters('woocommerce_gateway_icon', plugins_url('assets/images/payio-fast-checkout.png', __FILE__), $this->id));
                }

                $this->has_fields = true; // in case you need a custom credit card form, if simple offline gateway then should be false so the values are  true/false (bool).
                $this->method_title =  esc_textarea(__('Pay iO')); //the title of the payment method for the admin page
                $this->method_description =  esc_textarea(__('Pay instantly with your online bank app for a faster and more secure checkout. No cards or sign up required.')); // will be displayed on the options page
                $this->supports = array(
                    'products'
                );
                // Method with all the options fields
                $this->form_fields = (new Payio_Settings)->get_settings_fields();

                // Load the settings.
                $this->init_settings();
                $this->title = sanitize_text_field($this->get_option('title'));
                $this->description = sanitize_text_field($this->get_option('description'));
                $this->enabled = $this->get_option('enabled');

                // Site URL
                $this->siteUrl = get_site_url();

                //initialise webhook actions
                $this->initialise_webhooks();

                if (!function_exists('payio_custom_button_text') && $this->button_label == 'payio-inline.png') {
                    //check if function alread exists to prevent error on settings page
                    add_filter('woocommerce_available_payment_gateways', 'payio_custom_button_text');
                    function payio_custom_button_text($available_gateways)
                    {
                        if (!is_checkout()) return $available_gateways;  // stop doing anything if we're not on checkout page.
                        if (array_key_exists('payio_gateway', $available_gateways)) {
                            $available_gateways['payio_gateway']->order_button_text = __('Pay with Pay iO', 'woocommerce');
                        }
                        return $available_gateways;
                    }
                }
                //check if function alread exists to prevent error on settings page
                if (!function_exists('on_settings_save')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, 'on_settings_save', 10, 1);
                    function on_settings_save()
                    {
                        $_this = new WC_Payio_Gateway();
                        $_this->process_wc_settings();
                    };
                }
            }
            //get selected button label
            // Initiate express checkout if enabled in settings
            else if ($this->get_option('express_checkout') == 'yes') {
                include_once dirname(__FILE__) . '/includes/class-wc-gateway-payio-express-checkout.php';
                new Payio_Express_Checkout(
                    $this->button_label
                );
            }
        }

        function ec_ajax_response()
        {
            // Check for nonce security      
            if (!wp_verify_nonce($_POST['nonce'], 'ajax-nonce')) {
                die('Not authorised');
            }
            include_once dirname(__FILE__) . '/includes/class-wc-gateway-payio-custom-order.php';
            $gateway_url = (new Payio_Custom_Order)->create_new_order();
            if (!$gateway_url) {
                wp_send_json_error('Error: Unable to process checkout');
            } else {
                $response = array('success' => true, 'gateway_url' => $gateway_url);
                wp_send_json_success($response);
            }
            wp_die();
        }

        static function init_express_checkout()
        {
            new WC_Payio_Gateway(true);
        }

        public function initialise_webhooks()
        {
            $this->payio_webhooks = new Payio_Webhooks();
            add_action('woocommerce_api_' . $this->id  . '_update_order', array($this->payio_webhooks, 'payment_update_order'));
        }
        /**
         * public function process_wc_settings()

         *
         * @return void
         */
        public function process_wc_settings()
        {
            $logo_url = esc_url($this->upload_image());
            foreach ($this->get_form_fields() as $key => $field) {
                // if ('title' !== $this->get_field_type($field)) {
                try {
                    $post_data = $this->get_post_data();
                    //upload image

                    //set initial api key
                    $api_key = $this->settings['api_key'];

                    $has_error = false;
                    $sanitized_hex = null;

                    if ($key === 'logo_url' && $logo_url) {
                        //update logo url field with upload path
                        $this->settings[$key] = esc_url($logo_url);
                    } else if ($key === 'brand_color') {
                        $sanitized_hex = sanitize_hex_color($this->get_field_value($key, $field, $post_data));
                        $this->settings[$key] = $sanitized_hex;
                    } else if ($key === 'api_key') {
                        $new_api_key = $this->get_field_value($key, $field, $post_data);
                        //check if valid base64 encoded string
                        if (base64_encode(base64_decode($new_api_key, true)) === $new_api_key) {
                            $this->settings[$key] = $new_api_key;
                            $api_key = $new_api_key;
                        } else {
                            $has_error = true;
                            WC_Admin_Settings::add_error('Error: API key has invalid format');
                        }
                    } else {
                        $sanitized_text = sanitize_text_field($this->get_field_value($key, $field, $post_data));
                        $this->settings[$key] = $sanitized_text;
                    }
                } catch (Exception $e) {
                    $has_error = true;
                    WC_Admin_Settings::add_error($e->getMessage());
                }
            }
            // if error do not update woocommerce settings do not send them to api
            if ($has_error) return null;
            $plugin_active = sanitize_text_field($this->get_field_value('enabled', $field, $post_data)) ? 'true' : 'false';
            $sandboxed = sanitize_text_field($this->get_field_value('test_mode', $field, $post_data)) ? 'true' : 'false';

            $updated_settings = [
                "buttonMainColor"           => $sanitized_hex,
                "logoText"                  => sanitize_text_field($this->get_field_value('logo_alt', $field, $post_data)),
                "logoUrl"                   => $logo_url,
                "sandboxed"                 => $sandboxed,
                "pluginActive"              => $plugin_active,
                "platformBackendBaseUrl"    => esc_url_raw($this->siteUrl)
            ];

            $response = $this->post_settings_to_api($updated_settings, $api_key);
            //if no error from api update settings in wc
            if (!$response) return;
            //update settings values
            update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
        }

        /**
         * post_settings_to_api
         *
         * @return void
         */
        public function post_settings_to_api($updated_settings, $api_key)
        {
            $api_url = esc_url_raw($this->api_path . "merchant/setMerchantSettings");
            $api_response = $this->send_data_to_api('PUT', $updated_settings, $api_url, $api_key);
            if (!$api_response || !$api_response['code'] || $api_response['code'] !== 200) {
                WC_Admin_Settings::add_error($api_response['body'] ?? 'Unable to save settings');
                return false;
            }

            return true;
        }
        /**
         * upload_image
         *
         * @param  mixed $logo
         * @return void
         */
        function upload_image()
        {
            //validate user permissions
            if (!current_user_can('upload_files')) {
                WC_Admin_Settings::add_error('Unauthorised');
                return;
            }
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            if (empty($_FILES['woocommerce_payio_gateway_logo']['name'])) {
                //if no upload return silently
                return;
            }

            // We are only allowing images
            $allowedMimes = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'gif'          => 'image/gif',
                'png'          => 'image/png',
            );

            $fileInfo = wp_check_filetype(basename($_FILES['woocommerce_payio_gateway_logo']['name']), $allowedMimes);

            if (empty($fileInfo['type'])) {
                WC_Admin_Settings::add_error('Invalid logo image format. Valid formats are: jpg, jpeg, png, gif');
                return;
            }

            if (empty($fileInfo['ext']) || !@getimagesize($_FILES['woocommerce_payio_gateway_logo']['tmp_name'])) {
                WC_Admin_Settings::add_error('Logo upload error: invalid image file');
                return;
            }
            $uploaded_image = wp_handle_upload($_FILES['woocommerce_payio_gateway_logo'], array(
                'test_form' => false,
                'mimes'     => $allowedMimes,
            ));

            if ($uploaded_image && !isset($uploaded_image['error'])) {
                return esc_url($uploaded_image['url']);
            } else {
                WC_Admin_Settings::add_error('Logo upload error:' . $uploaded_image['error']);
            }
        }
        /**
         * send_data_to_api
         *
         * @param  string $type
         * @param  array $session_data
         * @param  string $url
         * @param  string $api_key
         * @return void
         */
        public function send_data_to_api($type, $session_data, $url, $api_key)
        {
            $request_headers = [
                'X-API-KEY' => (string) $api_key,
            ];

            $args = [
                'data_format' => 'body',
                'method' => $type,
                'headers' => $request_headers,
                'body' => $session_data,
                'sslverify' => true,
                'cookies' => array(),
            ];

            // call wordpress http curl function
            $api_response = wp_remote_post(esc_url_raw($url), $args);
            if (is_wp_error($api_response)) {
                return;
            }
            // set api response
            $api_response_code = $api_response['response']['code'];
            $response_body = wp_remote_retrieve_body($api_response);
            $response['code'] = $api_response_code;
            $response['body'] = $response_body;

            return $response;
        }

        function init_process_payment($order_id, $is_express_checkout)
        {
            //get current session data to POST to payio API
            $payio_session_data_request = new Payio_Session_Data($order_id);

            //  Get an instance of the WC_Payment_Gateways object
            $payment_gateways = WC_Payment_Gateways::instance();

            // Get the desired WC_Payment_Gateway object
            $payment_gateway = $payment_gateways->payment_gateways()[$this->id];

            // get settings values
            $api_key = $payment_gateway->settings['api_key'];

            // get customer info: name, address
            $session_data = $payio_session_data_request->get_session_data();
            //post cart data to api
            $api_response = $this->send_data_to_api('POST', $session_data,  esc_url_raw($this->api_path . "transaction/create"), $api_key);

            $api_response_body = json_decode($api_response['body'], true);

            $gateway_path = defined('PAYIO_GATEWAY_PATH') ? PAYIO_GATEWAY_PATH : 'https://secure.payio.co.uk/gateway/';

            $gateway_secure_url = esc_url_raw($gateway_path . $api_response_body['queryData']);

            $has_session_error = $api_response['code'] === 201 ? false : true;

            $error_message = $api_response_body['title'];

            if (!$is_express_checkout) {
                return array(
                    'result'   => $has_session_error ?  wc_add_notice('Error processing checkout: ' . $error_message, 'error') : 'success',
                    'redirect' => esc_url_raw($gateway_secure_url)
                );
            }
            //if is express checkout
            if ($has_session_error) {
                wc_add_notice('Error processing checkout: ' . $error_message, 'error');
                return;
            }
            return esc_url_raw($gateway_secure_url) ?? null;
        }

        /**
         * process_payment
         *
         * @param  string $order_id
         * @return array
         */
        function process_payment($order_id)
        {
            return $this->init_process_payment($order_id, false);
        }
    }
}
add_filter('woocommerce_payment_gateways', 'payio_add_gateway_class');

function payio_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Payio_Gateway';
    return $gateways;
}
