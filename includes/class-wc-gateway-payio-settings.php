<?php

/**
 * Settings for Pay iO Gateway.
 *
 * @package WooCommerce\Classes\Payment
 */
class Payio_Settings
{
    /**
     * default
     *
     * @var string
     */
    private $current_image;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
    }
    /**
     * get_settings_fields
     *
     * @return array
     */
    public function get_settings_fields()
    {
        $this->current_image = '';
        return array(
            'enabled' => array(
                'title' => __('Enable/Disable'),
                'type' => 'checkbox',
                'label' => __('Enable Pay iO Payment'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.'),
                'default' => 'PAY WITH BANK'
            ),
            'description' => array(
                'title' => 'Payment Description',
                'type' => 'textarea',
                'default' => 'Pay instantly with your online bank app for a faster and more secure checkout. No cards or sign up required.'
            ),
            'express_checkout' => array(
                'title'       => __('Enable express checkout'),
                'label'       => __('Enable express checkout'),
                'type'        => 'checkbox',
                'description' => __('Enable express checkout'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_mode' => array(
                'title'       => __('Enable test mode'),
                'label'       => __('Enable test mode'),
                'type'        => 'checkbox',
                'description' => __('Enable test mode'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'api_key'          => array(
                'title'       => __('API Key', 'woocommerce-payio-gateway'),
                'type'        => 'text',
                'description' => __('Your Pay iO dashboard (link to Pay iO dasboard) > Integration > API Keys', 'woocommerce-payio-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'branding'   => array(
                'title'       => __('Your Branding', 'woocommerce-payio-gateway'),
                'type'        => 'title',
                /* translators: %s: URL */
                'description' => sprintf(__('Input your brand data for use accross the checkout and merchant portal', 'woocommerce-payio-gateway'), 'https://payio.com'),
            ),
            'button_label'         => array(
                'title'       => __('Payment Button Label', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Select which Pay iO button label to display on your checkout and express checkout (if enabled) buttons.', 'woocommerce'),
                'default'     => 'payio',
                'desc_tip'    => true,
                'options'     => array(
                    'payio-inline.png' => __('Pay iO PAY WITH BANK', 'woocommerce-payio-gateway'),
                    'pay-now-inline.png' => __('PAY NOW', 'woocommerce-payio-gateway'),
                    'pay-with-bank-inline.png' => __('PAY WITH BANK', 'woocommerce-payio-gateway'),
                ),
            ),
            'logo_url' => array(
                'title'       => __('Logo Url', 'woocommerce-gateway-amazon-payments-advanced'),
                'type'        => 'text',
                'description' => __('Enter a plublicly accessible image url or upload a logo below', 'woocommerce-payio-gateway'),
                'default'     => $this->current_image
            ),
            'logo' => array(
                'title'       => __('Upload Logo', 'woocommerce-payio-gateway'),
                'type'        => 'file',
                'desc_tip'    => true,
                'description' => __('Maxsize: 1mb, supported file types: jpg, jpeg, png, gif ', 'woocommerce-payio-gateway'),
                'default'     => '',
            ),
            'logo_alt' => array(
                'title' => __('Logo image alternative'),
                'type' => 'text',
                'description' => __(''),
                'default' => ''
            ),
            'brand_color' => array(
                'title' => __('Brand colour', 'woocommerce-payio-gateway'),
                'type' => 'text',
                'description' => __('Input the primary colour of you brand. Exampe: #0022CC', 'woocommerce-payio-gateway'),
                'class' => 'colorpick'
            )
        );
    }
}
