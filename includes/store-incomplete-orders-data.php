<?php 
/**
* SAVE INCOMPLETE ORDERS DATA
*/

add_action('init', function() {
    add_action('wp_ajax_act_save_abandoned_cart', 'act_save_abandoned_cart');
    add_action('wp_ajax_nopriv_act_save_abandoned_cart', 'act_save_abandoned_cart');
    
    // Order Prop Integration - Hook into Order Prop's AJAX actions
    $order_prop_active = sk_is_order_prop_active();
    
    if ($order_prop_active) {
        add_action('wp_ajax_quick_order_submit', 'sk_capture_order_prop_data', 5);
        add_action('wp_ajax_nopriv_quick_order_submit', 'sk_capture_order_prop_data', 5);
        
        // Alternative method: Add JavaScript to capture form data
        add_action('wp_footer', 'sk_add_order_prop_tracking_script');
    }
});

/**
 * Check if Order Prop plugin is active
 */
function sk_is_order_prop_active() {
    return class_exists('Quick_Order_Solutions') || 
           in_array('order-prop/quick-order-solutions.php', apply_filters('active_plugins', get_option('active_plugins'))) ||
           function_exists('quick_order_solutions_init');
}

/**
 * Capture Order Prop data before order processing
 * This runs before Order Prop processes the order
 */
function sk_capture_order_prop_data() {
    // Only capture if we have form data and cart is not empty
    if (empty($_POST) || !WC()->cart || WC()->cart->is_empty()) {
        return;
    }
    
    // Map Order Prop fields to Cart Converter format
    $form_data = array();
    
    // Order Prop uses field names without 'billing_' prefix, so map them correctly
    $form_data['billing_first_name'] = sanitize_text_field($_POST['first_name'] ?? $_POST['billing_first_name'] ?? '');
    $form_data['billing_last_name'] = sanitize_text_field($_POST['last_name'] ?? $_POST['billing_last_name'] ?? '');
    $form_data['billing_phone'] = sanitize_text_field($_POST['phone'] ?? $_POST['billing_phone'] ?? '');
    $form_data['billing_email'] = sanitize_email($_POST['email'] ?? $_POST['billing_email'] ?? '');
    $form_data['billing_address_1'] = sanitize_text_field($_POST['address'] ?? $_POST['address_1'] ?? $_POST['billing_address_1'] ?? '');
    $form_data['billing_address_2'] = sanitize_text_field($_POST['address_2'] ?? $_POST['billing_address_2'] ?? '');
    $form_data['billing_city'] = sanitize_text_field($_POST['city'] ?? $_POST['billing_city'] ?? '');
    $form_data['billing_state'] = sanitize_text_field($_POST['state'] ?? $_POST['billing_state'] ?? '');
    $form_data['billing_postcode'] = sanitize_text_field($_POST['postcode'] ?? $_POST['billing_postcode'] ?? '');
    $form_data['billing_country'] = sanitize_text_field($_POST['country'] ?? $_POST['billing_country'] ?? 'BD');
    $form_data['payment_method'] = sanitize_text_field($_POST['payment_method'] ?? '');
    $form_data['order_comments'] = sanitize_textarea_field($_POST['order_note'] ?? $_POST['order_comments'] ?? '');
    $form_data['session_id'] = sk_get_or_create_session_id();
        
    // Only save if we have essential data
    if (empty($form_data['billing_first_name']) && empty($form_data['billing_phone']) && empty($form_data['billing_email'])) {
        return;
    }    
    // Save the abandoned cart data
    sk_save_order_prop_abandoned_cart($form_data);
}

/**
 * Save abandoned cart data from Order Prop
 */
function sk_save_order_prop_abandoned_cart($form_data) {
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

    // Get User IP Address
    $user_ip = sk_get_user_ip();

    $data = array(
        'user_ip' => sanitize_text_field($user_ip),
        'order_no' => 'OrderProp-' . uniqid(),
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
        'session_id' => sanitize_text_field($form_data['session_id']),
        'updated_status' => 'orderprop'
    );

    // Check for existing cart
    $existing_cart = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE phone = %s OR session_id = %s", 
        $form_data['billing_phone'], $form_data['session_id'])
    );

    if (!$existing_cart) {
        $wpdb->insert($table_name, $data);
    } else {
        $wpdb->update($table_name, $data, array('id' => $existing_cart->id));
    }
}

/**
 * Get or create session ID for Order Prop integration
 */
function sk_get_or_create_session_id() {
    if (!session_id()) {
        session_start();
    }
    
    if (!isset($_SESSION['sk_session_id'])) {
        $_SESSION['sk_session_id'] = 'sk_op_' . time() . '_' . wp_rand(1000, 9999);
    }
    
    return $_SESSION['sk_session_id'];
}

/**
 * Get user IP address
 */
function sk_get_user_ip() {
    $user_ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $user_ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $user_ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $user_ip = $_SERVER['REMOTE_ADDR'];
    }
    return trim($user_ip);
}

/**
 * Add JavaScript tracking for Order Prop forms
 */
function sk_add_order_prop_tracking_script() {
    if (!is_admin() && (is_shop() || is_product() || is_product_category() || is_woocommerce())) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {            
            var abandonedCartTimer;
            var isOrderSubmitted = false;
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            // Track form field changes in Order Prop popup
            $(document).on('input change', '#quick-order-form input, #quick-order-form select, #quick-order-form textarea', function() {
                clearTimeout(abandonedCartTimer);
                
                if (isOrderSubmitted) return;
                
                abandonedCartTimer = setTimeout(function() {
                    saveOrderPropAbandonedCart();
                }, 3000); // Save after 3 seconds of inactivity
            });
            
            // Save abandoned cart data
            function saveOrderPropAbandonedCart() {
                var $form = $('#quick-order-form');
                if ($form.length === 0) {
                    return;
                }
                                
                // Get form data
                var formData = new FormData($form[0]);
                var formObj = {};
                
                // Convert FormData to object
                for (var pair of formData.entries()) {
                    formObj[pair[0]] = pair[1];
                }
                                
                // Map Order Prop fields to Cart Converter format
                var cartData = {
                    billing_first_name: formObj.first_name || formObj.billing_first_name || '',
                    billing_last_name: formObj.last_name || formObj.billing_last_name || '',
                    billing_phone: formObj.phone || formObj.billing_phone || '',
                    billing_email: formObj.email || formObj.billing_email || '',
                    billing_address_1: formObj.address || formObj.address_1 || formObj.billing_address_1 || '',
                    billing_address_2: formObj.address_2 || formObj.billing_address_2 || '',
                    billing_city: formObj.city || formObj.billing_city || '',
                    billing_state: formObj.state || formObj.billing_state || '',
                    billing_postcode: formObj.postcode || formObj.billing_postcode || '',
                    billing_country: formObj.country || formObj.billing_country || 'BD',
                    payment_method: formObj.payment_method || '',
                    order_comments: formObj.order_note || formObj.order_comments || '',
                    session_id: 'op_js_' + Date.now()
                };
                
                // Only save if we have essential data
                if (!cartData.billing_first_name && !cartData.billing_phone && !cartData.billing_email) {
                    return;
                }                
                // Send via AJAX
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'act_save_abandoned_cart',
                        form_data: cartData
                    },
                });
            }
            
            // Mark order as submitted when form is submitted
            $(document).on('submit', '#quick-order-form', function() {
                isOrderSubmitted = true;
                clearTimeout(abandonedCartTimer);
            });
        });
        </script>
        <?php
    }
}

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
    
    // Log for debugging
    error_log('Cart Converter: act_save_abandoned_cart called with data: ' . print_r($form_data, true));

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

// Hook for Order Prop orders
add_action('woocommerce_new_order', 'track_order_and_delete_abandoned_cart', 10, 1);

function track_order_and_delete_abandoned_cart($order_id) {
    // Handle both order ID and order object
    if (is_object($order_id)) {
        $order = $order_id;
        $order_id = $order->get_id();
    } else {
        $order = wc_get_order($order_id);
    }

    if ($order) {
        // Get the email and phone from the order
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        // Delete abandoned cart data for the user
        if ($email || $phone) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sk_abandoned_carts';

            // Delete by phone, email, or session (for Order Prop orders)
            $delete_conditions = array();
            if ($phone) {
                $delete_conditions[] = $wpdb->prepare("phone = %s", $phone);
            }
            if ($email) {
                $delete_conditions[] = $wpdb->prepare("email = %s", $email);
            }
            
            // Also check for Order Prop specific entries
            $delete_conditions[] = "updated_status = 'orderprop'";
            
            if (!empty($delete_conditions)) {
                $where_clause = implode(' OR ', $delete_conditions);
                $wpdb->query("DELETE FROM $table_name WHERE $where_clause");
            }
        }
    }
}