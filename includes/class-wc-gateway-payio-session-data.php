<?php

/**
 * Class Payio_Session_Data class-payio-gateway-request file.
 *
 * @package PayioLtd/Payio
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Payio_Session_Data
 */
class Payio_Session_Data extends WC_Payment_Gateway
{
    /**
     * __construct
     *
     * @param  string $order_id
     * @return void
     */
    public function __construct($order_id)
    {
        $this->order_id = $order_id;
        $this->order = wc_get_order($order_id);
    }

    /**
     * get_customer_order_data
     *
     * @param  mixed $order
     * @return array
     */
    public function get_customer_order_data()
    {
        // get order object and order details
        $taxes = WC()->cart->get_taxes();
        $total_tax = 0;
        $shipping_tax = $this->order->shipping_tax;
        foreach ($taxes as $tax) $total_tax += $tax;
        $cart_tax = $total_tax - $shipping_tax;
        $chosen_shipping_method_id = WC()->session->get('chosen_shipping_methods', array())[0];

        //check if data is only digital
        $digital_only = true;
        foreach ($this->order->get_items() as $order_item) {
            $item = wc_get_product($order_item->get_product_id());
            if (!$item->is_virtual()) {
                $digital_only = false;
            }
        }

        // setup the data which has to be sent
        $shippingData = [
            'shippingType' => $chosen_shipping_method_id,
            'shippingAddress' => $this->order->shipping_address_1,
            'shippingAddress2' => $this->order->shipping_address_2,
            'shippingPostcode' => $this->order->shipping_postcode,
            'shippingCity' => $this->order->shipping_city,
            'countryCode' => $this->order->shipping_country,
        ];
        $customer_data = [
            'customerEmail' => $this->order->billing_email,
            'customerFirstName' => $this->order->shipping_first_name,
            'customerLastName' => $this->order->shipping_last_name,
            'customerPhoneNumber' => $this->order->billing_phone,
        ];
        $order_data = [
            'cartId' => $this->order->order_key,
            'digitalOnly' => $digital_only,
            'publicOrderReference' => $this->order->id,
            'totalTax' =>  $total_tax,
            'cartTax' => $cart_tax,
            'totalAmount' => WC()->cart->total, // use cart total to accomodate express checkout
            'currency' => $this->order->currency,
        ];
        return array_merge($customer_data, $order_data, $shippingData);
    }

    /**
     * get_cart_data
     *
     * @param  mixed $order
     * @return array
     */
    public function get_cart_data()
    {
        // get cart details
        $cart_items = [];
        $cart = WC()->cart->get_cart();

        $quantities = WC()->cart->get_cart_item_quantities();
        foreach ($cart as $cart_item) {
            $item  = $cart_item['data'];
            // Displaying the quantity if targeted product is in cart
            $item_id = $item->get_id();
            $item_name = $item->get_name();
            $item_price = $item->get_price();
            $item_qty = $quantities[$item_id];
            $product = wc_get_product($item_id);
            $item_image = wp_get_attachment_url($product->get_image_id());
            $item_sku = $product->get_sku();
            $cart_item = [
                "id" => $item_id,
                "name" => $item_name,
                "quantity" => $item_qty,
                "price" => $item_price,
                "sku" => $item_sku,
                "imageUrl" => $item_image
            ];

            $cart_items[] = $cart_item;
        }
        return $cart_items;
    }

    /**
     * get_available_shipping_methods
     *
     * @return array
     */
    public function get_available_shipping_methods()
    {
        foreach (WC()->cart->get_shipping_packages() as $package_id => $package) {
            // Check if a shipping for the current package exist
            if (WC()->session->__isset('shipping_for_package_' . $package_id)) {
                // Loop through shipping rates for the current package
                foreach (WC()->session->get('shipping_for_package_' . $package_id)['rates'] as $shipping_rate_id => $shipping_rate) {

                    $active_methods[] = array(
                        'rateId'     => $shipping_rate->get_id(), // same thing that $shipping_rate_id variable (combination of the shipping method and instance ID)
                        'methodId' => $shipping_rate->get_method_id(), // The shipping method slug
                        'instanceId' => $shipping_rate->get_instance_id(), // The instance ID
                        'name' => $shipping_rate->get_label(), // The label name of the method
                        'cost' => $shipping_rate->get_cost(), // The cost without tax
                    );
                }
                return $active_methods;
            }
        }
    }
    /**
     * get_session_data
     *
     * @param  array $order
     * @return array
     */
    public function get_session_data()
    {
        // get checkout url
        $payment_success_url = $this->order->get_checkout_order_received_url();
        $checkout_url = wc_get_checkout_url();
        $secure_token = base64_encode($this->order->id);
        $callbackData = [
            'paymentSuccessUrl' => esc_url_raw($payment_success_url),
            'checkoutUrl' => esc_url_raw($checkout_url),
            'secureToken' => $secure_token
        ];

        $customer_data = $this->get_customer_order_data();
        $line_items = array("lineItems" => $this->get_cart_data());
        $shipping_methods = array("shippingMethods" => $this->get_available_shipping_methods());
        $session_data =  array_merge($callbackData, $customer_data, $line_items, $shipping_methods);
        return $session_data;
    }
}
