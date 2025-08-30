<?php 
function enqueue_checkout_script() {
    if (function_exists('is_checkout') && is_checkout()) {
        wp_enqueue_script('checkout-script', get_template_directory_uri() . '/js/checkout-tracker.js', array('jquery'), null, true);

        // Start session if not started
        if (!session_id()) {
            session_start();
        }

        // Pass session ID and admin AJAX URL to JavaScript
        wp_localize_script('checkout-script', 'adminAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'session_id' => session_id()
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_checkout_script');

function _home_url(){
    $server_name = $_SERVER['SERVER_NAME'];
    return $server_name;
}

function sk_is_license_active(){
    
     $stored_api_key = get_option('sk_license_key_cc');
    error_log('stored api'. $stored_api_key);
    if (!$stored_api_key) {
        return false;
    }

    $check_data = get_option('sk_api_key_check_info', [
        'last_checked' => 0,
        'last_result' => false,
    ]);
    error_log(print_r($check_data, true));
    $now = time();
    $six_hours = 6 * HOUR_IN_SECONDS;

    // Check if 6 hours have passed
    if ($now - intval($check_data['last_checked']) >= $six_hours) {
        // Call your license validation function
        $response = sk_license_check_manager($stored_api_key);

        // Save the check time and result
        $check_data = [
            'last_checked' => $now,
            'last_result'  => $response
        ];
        update_option('sk_api_key_check_info', $check_data);

        if ($response) {
            return true;
        } else {
            update_option('sk_license_key_cc', '');
            return false;
        }
    }

    // Use last known result if it's too soon to recheck
    return $check_data['last_result'];
}

function get_cart_converter_plugin_id() {
    // Load plugin data
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

       // Use the constant defined in the main plugin file
    if (!defined('SK_CART_CONVERTER_FILE')) {
        return null;
    }

    $plugin_data = get_plugin_data(SK_CART_CONVERTER_FILE);
    $plugin_name = $plugin_data['Name']; // e.g. "Cart Converter" or "SKL Customer Order data backup with google sheet"
    // Step 1: Reverse mapping: custom name => API name
    $name_map = [
        'Cart Converter' => 'Cart converter',
        'Fake Order Blocker' => 'Fake order',
        'SKL Customer Order data backup with google sheet' => 'Google sheet',
        'OrderPop - WooCommerce Buy Now Plugin'=> 'Order confirmation popup',
    ];

    // Get the original API name from your customized name
    $api_plugin_name = $name_map[$plugin_name] ?? $plugin_name;
    // Step 2: Call API
    $response = wp_remote_get('https://portalapi.servicekey.com.bd/api/plugin/list');
    if (is_wp_error($response)) {
        return null;
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['success']) || !$data['success'] || !isset($data['result'])) {
        return null;
    }

    // Step 3: Match plugin name and return ID
    foreach ($data['result'] as $plugin_info) {
        if ($plugin_info['name'] === $api_plugin_name) {
            return $plugin_info['id'];
        }
    }
}
// Check domain authorization status
function sk_license_check_manager($license_key='') {

    // Get the current site URL
    $domain = _home_url();
    $key = $license_key ? $license_key : get_option('sk_license_key_cc');
    $plugin_id = get_cart_converter_plugin_id();
    // API endpoint URL with query parameters
    $api_url = add_query_arg([
        'domain' => $domain,
        'code'   => $key,
        'plugin_id' => $plugin_id,
    ], 'https://portalapi.servicekey.com.bd/api/plugin-verifications'); 
    // Make the GET request
    $response = wp_remote_get($api_url, [
        'headers' => ['Accept' => 'application/json'],
    ]);

    // Handle errors
    if (is_wp_error($response)) {
        return false; // Assume not authorized if API request fails
    }

    // Decode the response
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    // Check API response
    if (!empty($data['success']) && (bool)$data['result'] === true) {
        return true;
    }

    return false;
}

// Handle delete action
function sk_handle_cart_delete() {
    if (
        isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) &&
        $_GET['action'] === 'delete' &&
        wp_verify_nonce($_GET['_wpnonce'], 'delete_cart_' . $_GET['id'])
    ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sk_abandoned_carts';
        $cart_id = intval($_GET['id']);

        $deleted = $wpdb->delete($table_name, ['id' => $cart_id]);

        $redirect_url = admin_url('admin.php?page=incomplete-orders');
        $redirect_url = add_query_arg('delete', $deleted ? 'success' : 'fail', $redirect_url);

        wp_redirect($redirect_url);
        exit;
    }
}
add_action('admin_init', 'sk_handle_cart_delete');
