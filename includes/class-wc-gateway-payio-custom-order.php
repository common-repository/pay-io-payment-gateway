<?php

/**
 * Class Payio_Custom_Order class-payio-gateway-request file.
 *
 * @package PayioLtd/Payio
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Payio_Custom_Order
 */
class Payio_Custom_Order
{
    public function create_new_order()
    {
        $checkout = WC()->checkout();
        $order_id = $checkout->create_order(array());
        $order = wc_get_order($order_id);
        $user_id = get_current_user_id();
        $customer_data = WC()->customer;

        // get logged in user details`
        if ($user_id !== 0) {
            $user_info = get_userdata($user_id);
            $email = $user_info->user_email;
            $firstname = $user_info->user_firstname;
            $lastname = $user_info->user_lastname;
            $company = $user_info->company;
        }
        // get address from cart
        $shipping_address_1 = $customer_data->shipping_address_1;
        $shipping_address_2 = $customer_data->shipping_address_2;
        $shipping_city       = $customer_data->shipping_city;
        $shipping_state      = $customer_data->shipping_state;
        $shipping_postcode   = $customer_data->shipping_postcode;
        $shipping_country    = $customer_data->shipping_country;

        //create full address for new order
        $address = array(
            'first_name' => $firstname ?? '',
            'last_name' => $lastname ?? '',
            'company'    => $company ?? '',
            'email'      => $email ?? '',
            'phone'      => '',
            'address_1'  => empty($shipping_address_1) ? '' : $shipping_address_1,
            'address_2'  => $shipping_address_2 ?? '',
            'city'       => $shipping_city ?? '',
            'state'      => $shipping_state ?? '',
            'postcode'   => empty($shipping_postcode) ? '' : $shipping_postcode,
            'country'    => $shipping_country ?? ''
        );

        $this->set_customer_details($order, $address);
        update_post_meta($order_id, '_customer_user', get_current_user_id());
        $this->create_shipping($order);
        $order->update_status('pending');
        //call checkout function
        $gateway_secure_url = (new WC_Payio_Gateway)->init_process_payment(($order->ID), true);
        return esc_url_raw($gateway_secure_url) ?? null;
    }
    public function set_customer_details($order, $address)
    {
        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
    }
    public function create_shipping($order)
    {
        $current_session = WC()->session;
        $chosen_shiping_method = $current_session->get('chosen_shipping_methods');

        $shipping_item = new WC_Order_Item_Shipping();
        // Set the array for tax calculations
        $shipping_tax_item = array(
            'country' => $shipping_country ?? 'GB',
            'state' => '',
            'postcode' => '',
            'city' => '',
        );
        // Loop through shipping packages from WC_Session (They can be multiple in some cases)
        foreach (WC()->cart->get_shipping_packages() as $package_id => $package) {
            // Check if a shipping for the current package exist
            if (WC()->session->__isset('shipping_for_package_' . $package_id)) {
                // Loop through shipping rates for the current package
                foreach (WC()->session->get('shipping_for_package_' . $package_id)['rates'] as $shipping_rate_id => $shipping_rate) {

                    //get info of chosen shipping method
                    if ($chosen_shiping_method[0] == $shipping_rate->get_id()) {
                        $shipping_rate_id     = $shipping_rate->get_id();
                        $shipping_label_name  = $shipping_rate->get_label();
                        $shipping_cost        = $shipping_rate->get_cost() ?? 0;
                    }
                }
            }
        }

        $shipping_item->set_method_title($shipping_label_name);
        $shipping_item->set_method_id($shipping_rate_id);
        $shipping_item->set_total($shipping_cost);
        $shipping_item->calculate_taxes($shipping_tax_item);

        //add shipping item to order
        $order->add_item($shipping_item);
    }
    public function sanitize_postcode($postcode)
    {
        return strtoupper(preg_replace('/\s+/', '', $postcode));
    }
    public function get_new_shipping_methods($customer_details, $order)
    {
        $new_shipping_country = sanitize_text_field($customer_details['countryCode']);
        $new_shipping_postcode = $this->sanitize_postcode($customer_details['shippingPostcode']);
        $allowed_shipping_zone = $this->get_available_shipping_zones(array("country" => $new_shipping_country, "postcode" => $new_shipping_postcode));

        //if no shipping zones
        if (!$allowed_shipping_zone) return;

        // get shipping methods for each zone and return their unique id
        $available_shipping_methods = $this->get_available_shipping_methods($allowed_shipping_zone, null);

        return $available_shipping_methods;
    }
    public function get_available_shipping_methods($allowed_shipping_zone, $selected_shipping_id)
    {
        //get new shiping zone
        $shipping_zone = WC_Shipping_Zones::get_zone_by('zone_id', $allowed_shipping_zone);

        // Get an array of available shipping methods for the selected shipping zone
        $shipping_methods = $shipping_zone->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->enabled == 'yes') {
                $rateId = $shipping_method->id . ":" . $shipping_method->instance_id;
                $shipping_item = [
                    'rateId' => $rateId,
                    'methodId' => $shipping_method->id,
                    'instanceId' => $shipping_method->instance_id,
                    'name' => $shipping_method->title, // The label name of the method
                    'cost' => $shipping_method->cost ?? 0, // The cost
                ];

                $active_methods[] = $shipping_item;
            };
            // return single item if selected_shipping_id exists 
            if ($selected_shipping_id && $selected_shipping_id === $rateId) {
                return $shipping_item;
            }
        }
        //return null if we are looking for specific shipping rate
        return !$selected_shipping_id ? $active_methods : null;
    }
    public function get_all_shipping_zones()
    {
        // Get all your existing shipping zones IDS
        $zones = WC_Shipping_Zones::get_zones();

        //order matches that of shipping order in admin
        return array_keys(array('') + $zones);
    }
    public function get_available_shipping_zones($address_data)
    {
        $country = sanitize_text_field($address_data['country']);
        $postcode = sanitize_text_field($address_data['postcode']);

        $zone_ids = $this->get_all_shipping_zones();
        //if no zones return
        if (empty($zone_ids)) return [];

        //get all zones which poscode rules from db
        if ($postcode && $country) {
            $postcode_zone_ids = $this->get_postcode_rule_zone_ids($postcode, $country);
        }
        // Loop through shipping zones IDs
        foreach ($zone_ids as $zone_id) {

            // Get the shipping Zone object
            $shipping_zone = new WC_Shipping_Zone($zone_id);
            $wc_contries = new WC_Countries();
            $continent_code = $wc_contries->get_continent_code_for_country($country);

            // Loop through Zone locations
            foreach ($shipping_zone->get_zone_locations() as $location) {
                if ($location->code === $country || $location->code === $continent_code) {
                    $valid_zone_ids[] = $zone_id;
                }
            }
        }
        // merge and remove duplicates
        $available_shipping_zones = array_unique(array_merge($valid_zone_ids, $postcode_zone_ids));


        //get first available zone from sort order
        return array_values($available_shipping_zones)[0];
    }
    public function get_postcode_rule_zone_ids($postcode, $country)
    {
        global $wpdb;
        $postcode_locations = $wpdb->get_results("SELECT zone_id, location_code FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE location_type = 'postcode';");
        $postcode_validation_matches = wc_postcode_location_matcher($postcode, $postcode_locations, 'zone_id', 'location_code', $country);
        $postcode_zone_ids = array_keys($postcode_validation_matches);
        return $postcode_zone_ids;
    }
    public function update_order_and_get_totals($customer_details, $order, $selected_shipping_id)
    {
        $new_shipping_postcode = $this->sanitize_postcode($customer_details['shippingPostcode']);
        $new_shipping_country = sanitize_text_field($customer_details['countryCode']);
        $zone_ids = $this->get_all_shipping_zones();

        //find selected shipping method
        foreach ($zone_ids as $zone) {
            $new_shipping_method = $this->get_available_shipping_methods($zone, $selected_shipping_id);
            if ($new_shipping_method) break;
        }

        //update client data
        $this->update_address($customer_details, $order);

        // Array for tax calculations
        $tax_item = array(
            'country'  => $new_shipping_country,
            'postcode' =>  $new_shipping_postcode,
        );

        // find current shipping item and update with new data
        foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
            $shipping_item->set_method_title($new_shipping_method['name']);
            $shipping_item->set_method_id($new_shipping_method['rateId']); // set an existing Shipping method rate ID
            $shipping_item->set_total($new_shipping_method['cost']);
            $shipping_item->calculate_taxes($tax_item);
            $shipping_item->save();
        }

        $order->calculate_taxes($tax_item);
        $order->calculate_totals();

        $new_totals = [
            'totalTax' =>  $order->get_total_tax(),
            'totalAmount' => $order->get_total()
        ];

        return $new_totals;
    }
    public function update_address($customer_details, $order)
    {
        //create full address for new order
        $address = array(
            'first_name' => sanitize_text_field($customer_details['firstName']),
            'last_name'  => sanitize_text_field($customer_details['lastName']),
            'email'      => sanitize_email($customer_details['email']),
            'phone'      => wc_sanitize_phone_number($customer_details['phoneNumber']),
            'address_1'  => sanitize_text_field($customer_details['shippingAddress']),
            'address_2'  => sanitize_text_field($customer_details['shippingAddress2']),
            'city'       => sanitize_text_field($customer_details['shippingCity']),
            'state'      => '', // not included in gateway
            'postcode'   => sanitize_text_field($customer_details['shippingPostcode']),
            'country'    => sanitize_text_field($customer_details['countryCode']),
        );

        //update details
        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
    }
}
