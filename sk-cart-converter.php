<?php
/*
Plugin Name: Cart Converter
Description: Tracks abandoned carts in WooCommerce and stores specific data in a custom table.
Version:           1.1.0
Author:            Service Key
Author URI:        https://servicekey.com.bd/
License:           GNU General Public License v2 or later
Text Domain:       sk-cart-converter
*/
 
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
define('SK_CART_CONVERTER_FILE', __FILE__);
define( 'SS_VERSION', '1.1.0' );
define( 'SS_ASSETS_PATH', plugin_dir_url( __FILE__ ) . 'assets' );

// Create the custom table on plugin activation
register_activation_hook(__FILE__, 'act_create_abandoned_carts_table');

function act_create_abandoned_carts_table() {

    add_option('sk_license_key_cc', '');

    global $wpdb;
    $table_name = $wpdb->prefix . 'sk_abandoned_carts';
    $charset_collate = $wpdb->get_charset_collate(); 

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_no VARCHAR(255) DEFAULT '',
        products TEXT,
        user_name VARCHAR(255) DEFAULT '',
        first_name VARCHAR(255) DEFAULT '',
        last_name VARCHAR(255) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        phone VARCHAR(255) DEFAULT '',
        address TEXT,
        additional_text TEXT,
        checkout_method VARCHAR(255) DEFAULT '',
        session_id VARCHAR(255) DEFAULT '',
        user_ip VARCHAR(50) DEFAULT '',
        updated_status VARCHAR(255) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Check if user_ip column exists, and add it if not (for safety with existing users)
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'user_ip'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN user_ip VARCHAR(255) DEFAULT ''");
    }
}

register_deactivation_hook(__FILE__, 'sk_cart_converter_deactivation');
function sk_cart_converter_deactivation() {
    update_option('sk_license_key_cc', '');
}

/**
 * Its an additional code to add a new col called user_ip 
 * as we alrady had existing users and activation_hook is not going to call for them
 * So we are giving a check and add this new col is not exists
 * */ 
function sk_add_column_if_not_exists($table, $column, $definition, $after = '') {
    global $wpdb;

    $exists = $wpdb->get_results(
        $wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $column)
    );

    if (empty($exists)) {
        $after_sql = $after ? "AFTER `$after`" : '';
        $wpdb->query("ALTER TABLE `$table` ADD `$column` $definition $after_sql");
    }
}

function sk_check_and_add_missing_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'sk_abandoned_carts';

    sk_add_column_if_not_exists($table, 'user_ip', 'VARCHAR(255) DEFAULT \'\'', 'session_id');
   //  sk_add_column_if_not_exists($table, 'updated_status', "VARCHAR(50) DEFAULT 'none'", 'user_ip');
    $added = sk_add_column_if_not_exists($table, 'updated_status', "VARCHAR(50) DEFAULT 'none'", 'user_ip');
    // If the column was just added, update all existing rows with 'none'
    if ($added) {
        $wpdb->query("UPDATE $table SET updated_status = 'none' WHERE updated_status = '' OR updated_status IS NULL");
    }
}
add_action('init', 'sk_check_and_add_missing_columns');

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (function_exists('sk_is_license_active') && sk_is_license_active()) {
    require 'plugin-update-checker/plugin-update-checker.php';

    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://servicekey.com.bd/update/cart-converter/update.json',
        __FILE__,
        'sk-cart-converter'
    );
}

require_once ( 'includes/functions.php' );
require_once ( 'includes/enqueue.php' );
require_once ( 'includes/dashboard-menus.php' );
require_once ( 'includes/store-incomplete-orders-data.php' );
require_once ( 'includes/display-cart-items.php' );
require_once ( 'includes/edit-item.php' );
require_once ( 'includes/product-search.php' );
require_once ( 'includes/create-an-order.php' );