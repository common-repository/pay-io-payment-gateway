<?php

/**
 * Class Payio_Express_Checkout class-payio-gateway-request file.
 *
 * @package PayioLtd/Payio
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payio_Express_Checkout
 */
class Payio_Express_Checkout
{
    public function __construct($selected_button)
    {
        $this->checkout_button = '/assets/images/' . esc_textarea($selected_button ? $selected_button : 'payio-inline.png');
        if (!function_exists('payio_express_checkout_cart_button')) {
            add_action('woocommerce_proceed_to_checkout', array($this, 'payio_express_checkout_cart_button'), 20);
        }

        if (!function_exists('include_scripts')) {
            $this->include_scripts();
        }

        if (!function_exists('payio_express_checkout_mini_cart_button')) {
            add_action('woocommerce_widget_shopping_cart_buttons', array($this, 'payio_express_checkout_mini_cart_button'), 20);
        }
    }
    public function payio_express_checkout_cart_button()
    {
        echo '<center class="' . esc_attr('payio-express-button-wrap') . '">
                <button class="' . esc_attr('payio-express-button') . '">
                    <img src="' . esc_url_raw(plugins_url($this->checkout_button, dirname(__FILE__))) . '" alt="' . esc_attr('Check out with Pay iO') . '" />
                </button>
                <div class="' . esc_attr('payio-express-desc') . '"> ' . esc_textarea(__('Faster checkout, more secure')) . '</div>
            </center>';
    }
    public function payio_express_checkout_mini_cart_button()
    {

        echo '<center class="' . esc_attr('payio-express-button-wrap payio-express-button-mini-cart') .  '">
                <button class="' . esc_attr('payio-express-button') . '">
                    <img src="' . esc_url_raw(plugins_url($this->checkout_button, dirname(__FILE__))) . '" alt="' . esc_attr('Check out with Pay iO') . '" />
                </button>
                <div class="' . esc_attr('payio-express-desc') . '"> ' . esc_textarea(__('Faster checkout, more secure')) . '</div>
            </center>';
    }

    public static function include_scripts()
    {
        //css
        wp_register_style('payio-fonts', plugins_url('/assets/css/payio-fonts.css', dirname(__FILE__)));
        wp_register_style('payio-express-checkout-style', plugins_url('/assets/css/payio-express-checkout.css', dirname(__FILE__)));
        wp_enqueue_style('payio-fonts');
        wp_enqueue_style('payio-express-checkout-style');
        //inclide ajax scripts
        wp_register_script('main-js', plugins_url('/assets/js/payio-express-checkout.js', dirname(__FILE__)), array(), rand(), true);
        wp_enqueue_script('main-js');
        wp_localize_script('main-js', 'ajax_var', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'action' => 'ec_ajax_response',
            'nonce' => wp_create_nonce('ajax-nonce')
        ));
    }
}
