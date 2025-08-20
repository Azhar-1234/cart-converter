<?php 
/**
* ADMIN MENUS
*/ 

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('admin_menu', 'act_add_admin_menu');

function act_add_admin_menu() {
    // Add main menu "Cart Converter" without its own page
    add_menu_page(
        'Cart Converter', // Page title
        'Cart Converter', // Menu title
        'manage_options',  // Capability
        'cart-converter-dashboard', // Menu slug (points to Dashboard)
        'act_display_dashboard', // Callback function
        'dashicons-cart', // Icon
        30 // Position
    );

    // Add Dashboard Page as a submenu (same slug as main menu to avoid duplicate)
    add_submenu_page(
        'cart-converter-dashboard', // Parent menu slug (Cart Converter)
        'Dashboard', // Page title
        'Dashboard', // Menu title
        'manage_options',
        'cart-converter-dashboard', // Same slug to make it default
        'act_display_dashboard'
    );

    if( sk_is_license_active() ){
        // Add Incomplete Orders Page as a submenu
        add_submenu_page(
            'cart-converter-dashboard',
            'Incomplete Orders',
            'Incomplete Orders',
            'manage_options',
            'incomplete-orders',
            'act_display_abandoned_carts'
        );

        // Add Edit Cart Page (Hidden)
        add_submenu_page(
            null, // Hidden from menu
            'Edit Abandoned Cart',
            'Edit Abandoned Cart',
            'manage_options',
            'edit-incomplete-orders',
            'act_edit_abandoned_cart'
        );
    }
    
}


function act_display_dashboard(){
    require_once ( 'dashboard.php' );
}