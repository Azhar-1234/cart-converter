<?php 
/**
* ALL ENQUEUE FILES
*/ 

add_action( 'wp_enqueue_scripts', function() { 
    wp_enqueue_script( 'ss-default-js', SS_ASSETS_PATH. '/js/checkout-tracker.js', array( 'jquery' ), SS_VERSION, true );

    if (is_checkout() && !session_id()) {
        session_start();
    }
 
    wp_localize_script( 'ss-default-js', 'adminAjax', array( 
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'session_id' => session_id()
    ) );
});

add_action('admin_enqueue_scripts', function() {
    // Enqueue DataTables CSS
    wp_enqueue_style( 'oft-datatables-css', 'https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css' );
    // Enqueue jQuery and DataTables JS
    wp_enqueue_script( 'oft-datatables-js', 'https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js', array( 'jquery' ), SS_VERSION, true );

    wp_enqueue_style( 'ss-core-style', SS_ASSETS_PATH. '/sk-style.css', array(), SS_VERSION );

    wp_enqueue_script('ss-admin-product-delete', SS_ASSETS_PATH . '/js/delete-product-on-edit.js', array(), SS_VERSION, true);
    wp_enqueue_script('ss-admin-product-search', SS_ASSETS_PATH . '/js/admin.js', array('jquery'), SS_VERSION, true);
    wp_localize_script('ss-admin-product-search', 'adminAjax', array('ajax_url' => admin_url('admin-ajax.php')));

    // chart js
    wp_enqueue_script('ss-chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(''), SS_VERSION, true);
    wp_enqueue_script('ss-chart-js-report', SS_ASSETS_PATH . '/js/chart.js', array(''), SS_VERSION, true);
});
