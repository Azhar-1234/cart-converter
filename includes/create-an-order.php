<?php 
/**
* CREATE ORDER FUNCTIONALITY
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('wp_ajax_act_redirect_to_checkout', 'act_redirect_to_checkout');
add_action('wp_ajax_nopriv_act_redirect_to_checkout', 'act_redirect_to_checkout');

function act_redirect_to_checkout() {

    if (!isset($_POST['cart_data'])) {
        wp_send_json_error('Invalid cart data.');
    }

    $data = $_POST['cart_data'];
    $data_id = $data['id'];
    $data_id = (int) $data_id; 

   // wp_send_json_success(gettype($data_id));

    // Create a new WooCommerce order
    $order = wc_create_order();

    // Add products dynamically
    foreach ($data['products'] as $product) {
        $product_id = intval($product['id']);
        $quantity = intval($product['quantity']);
        
        $wc_product = wc_get_product($product_id);
        if ($wc_product && $quantity > 0) {
            $order->add_product($wc_product, $quantity);
        }
    }

    // Setup address
    $address = [
        'first_name' => sanitize_text_field($data['first_name']),
        'last_name'  => sanitize_text_field($data['last_name']),
        'email'      => sanitize_email($data['email']),
        'phone'      => sanitize_text_field($data['phone']),
        'address_1'  => sanitize_textarea_field($data['address']),
        'city'       => '',        // You can set dynamically if needed
        'state'      => '',
        'postcode'   => '',         // Optional
        'country'    => 'BD',
    ];

    $order->set_address($address, 'billing');
    $order->set_address($address, 'shipping');

    // Optional: Add shipping method
    $shipping = new WC_Order_Item_Shipping();
    $shipping->set_method_title('Free shipping');
    $shipping->set_method_id('free_shipping:1'); // Existing method ID
    $shipping->set_total(0);
    $order->add_item($shipping);

    // Optional: Add additional text as order note or meta
    if (!empty($data['additional_text'])) {
        $order->add_order_note(wp_kses_post($data['additional_text']));
    }

    // Finalize order
    $order->calculate_totals();
    $order->update_status('completed');
    $order->save();
    

    // ✅ Update custom table if order was created successfully
    if ($data_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sk_abandoned_carts';
        $wpdb->update(
            $table_name,
            ['updated_status' => 'confirmed'], // data to update
            ['id' => $data_id],               // WHERE clause
            ['%s'],                           // format for value
            ['%d']                            // format for WHERE
        );
    }

    // Return success
    wp_send_json_success([
        'message' => 'Order created successfully',
        'order_id' => $order->get_id(),
        'redirect' => admin_url('admin.php?page=incomplete-orders'),
    ]);
}


add_filter('woocommerce_checkout_get_value', 'prefill_checkout_fields', 10, 2);

function prefill_checkout_fields($value, $input) {
    if (!WC()->session) {
        return $value;
    }

    $cart_data = WC()->session->get('abandoned_cart_data');

    if ($cart_data) {
        switch ($input) {
            case 'billing_first_name':
                return $cart_data['first_name'];
            case 'billing_last_name':
                return $cart_data['last_name'];
            case 'billing_email':
                return $cart_data['email'];
            case 'billing_phone':
                return $cart_data['phone'];
            case 'billing_address_1':
                return $cart_data['address'];
            case 'order_comments':
                return $cart_data['additional_text'];
        }
    }

    return $value;
}