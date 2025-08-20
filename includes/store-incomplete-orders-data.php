<?php 
/**
* SAVE INCOMPLETE ORDERS DATA
*/

add_action('init', function() {
    add_action('wp_ajax_act_save_abandoned_cart', 'act_save_abandoned_cart');
    add_action('wp_ajax_nopriv_act_save_abandoned_cart', 'act_save_abandoned_cart');
});

function act_save_abandoned_cart() {

    if (!isset($_POST['form_data'])) {
        wp_send_json_error('No data received');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sk_abandoned_carts';

    // Retrieve cart contents
    $cart = WC()->cart->get_cart();
    $products = array();

    foreach ($cart as $cart_item) {
        $product = $cart_item['data'];
        $products[] = array(
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'quantity' => $cart_item['quantity'],
            'price' => $product->get_price()
        );
    }

    // Retrieve form data safely
    $form_data = $_POST['form_data'];

    // Get User IP Address
    $user_ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $user_ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $user_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $user_ip = $_SERVER['REMOTE_ADDR'];
    }

    $data = array(
        'user_ip' => sanitize_text_field($user_ip), // Sanitizing IP address
        'order_no' => 'Abandoned-' . uniqid(),
        'products' => serialize($products),
        'user_name' => sanitize_text_field($form_data['billing_first_name'] . ' ' . $form_data['billing_last_name']),
        'first_name' => sanitize_text_field($form_data['billing_first_name']),
        'last_name' => sanitize_text_field($form_data['billing_last_name']),
        'email' => sanitize_email($form_data['billing_email']),
        'phone' => sanitize_text_field($form_data['billing_phone']),
        'address' => serialize(array(
            'billing_address_1' => sanitize_text_field($form_data['billing_address_1']),
            'billing_address_2' => sanitize_text_field($form_data['billing_address_2']),
            'billing_city' => sanitize_text_field($form_data['billing_city']),
            'billing_state' => sanitize_text_field($form_data['billing_state']),
            'billing_postcode' => sanitize_text_field($form_data['billing_postcode']),
            'billing_country' => sanitize_text_field($form_data['billing_country'])
        )),
        'additional_text' => sanitize_textarea_field($form_data['order_comments']),
        'checkout_method' => sanitize_text_field($form_data['payment_method']),
        'session_id' => sanitize_text_field($form_data['session_id']), // Add session ID 
        'updated_status' => 'none' // Add session ID 
    );

   // wp_send_json_success($data);

    $existing_cart = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE phone = %s OR session_id = %s", $form_data['billing_phone'], $form_data['session_id'])
    );

    if (!$existing_cart) {
        $wpdb->insert($table_name, $data);
    } else {
        $wpdb->update($table_name, $data, array('id' => $existing_cart->id));
    }

    wp_send_json_success('Cart saved successfully');
}

add_action('woocommerce_thankyou', 'track_order_and_delete_abandoned_cart', 10, 2);
add_action('woocommerce_after_checkout_validation', 'track_order_and_delete_abandoned_cart', 10, 1);

function track_order_and_delete_abandoned_cart($order_id) {
    // Get the order object
    $order = wc_get_order($order_id);

    if ($order) {
        // Get the email and phone from the order
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();

        // Delete abandoned cart data for the user
        if ($email || $phone) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sk_abandoned_carts';

            // Delete the abandoned cart data for the user
            $wpdb->delete($table_name, array('phone' => $phone));
        }
    }
}