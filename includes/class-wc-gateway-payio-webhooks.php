<?php

class Payio_Webhooks
{
    /**
     * payment_update_order
     *
     * @return void
     */
    public function payment_update_order()
    {
        include_once dirname(__FILE__) . '/includes/class-wc-gateway-payio-custom-order.php';
        $data = json_decode(file_get_contents('php://input'), true);
        // unpack data
        $customer_details = $data['customerDetails'];
        $order_id = sanitize_text_field($data["orderId"]);
        $status = sanitize_text_field($data["status"]);
        $active_methods[] = array(
            'rateId' => $shipping_method->id,
        );
        $selected_shipping_id = sanitize_text_field($data["selectedShippingId"]);

        if (is_null($order_id)) {
            http_response_code(400);
            echo json_encode('Failed: No order ID');
            die();
        }

        $order = wc_get_order($order_id);

        if (empty($order)) {
            http_response_code(400);
            echo json_encode('Failed: No order exists with that order number');
            die();
        }

        // if selectedShippingId update totals
        if ($selected_shipping_id) {
            $totals_response = (new Payio_Custom_Order)->update_order_and_get_totals($customer_details, $order, $selected_shipping_id);
            // if no response
            if (!$totals_response) {
                http_response_code(400);
                echo json_encode('Failed: Unable to update totals');
                die();
            }
            echo json_encode($totals_response);
            die();
        }
        //if status exists change status
        if ($status) {
            $update_status_response = $this->update_status($order, $status);
            echo $update_status_response;
            die();
        };
        if (!$customer_details) {
            http_response_code(400);
            echo json_encode('Failed: Insufficient address details provided');
            die();
        }

        $new_shipping_methods_response = (new Payio_Custom_Order)->get_new_shipping_methods($customer_details, $order);

        http_response_code(200);
        if (!$new_shipping_methods_response) {
            echo json_encode(array());
            die();
        }
        echo json_encode($new_shipping_methods_response);
        die();
    }

    public function update_status($order, $status)
    {
        if ($status === "PENDING") {
            $order->update_status('on-hold');
            http_response_code(200);
            return json_encode('Payment status updated to on-hold');
        } else if ($status === "COMPLETED") {
            $order->update_status('processing');
            http_response_code(200);
            return json_encode('Payment status updated to processing');
        } else {
            http_response_code(200);
            return json_encode('Failed: Incorrect status provided');
        };
    }
}
