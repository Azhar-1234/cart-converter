<?php 
/**
* PRODUCT SEARCH
*/

add_action('init', function() {
    add_action('wp_ajax_search_products', 'search_products_function');
    add_action('wp_ajax_nopriv_search_products', 'search_products_function');

    add_action('wp_ajax_get_product_variations', 'get_product_variations_function');
    add_action('wp_ajax_nopriv_get_product_variations', 'get_product_variations_function');
});

function search_products_function() {
    global $wpdb;
    $query = '%' . $wpdb->esc_like($_POST['query']) . '%';

    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s LIMIT 10",
        $query
    ));

    if ($products) {
        $response = [];
        foreach ($products as $product) {
            $product_obj = wc_get_product($product->ID);
            $product_type = $product_obj->get_type();
        
            if ($product_type === 'simple') {
                // Add simple product to response
                $response[] = [
                    'id' => $product_obj->get_id(),
                    'name' => $product_obj->get_name(),
                    'price' => $product_obj->get_price(),
                    'type' => 'simple', // Add product type
                ];
            } elseif ($product_type === 'variable') {
                // Get variation IDs
                $variation_ids = $product_obj->get_children();
        
                // Loop through each variation ID
                $variations = [];
                foreach ($variation_ids as $variation_id) {
                    $variation = wc_get_product($variation_id); // Get the variation object
                    if ($variation) {
                        // Add variation details to the array
                        $variations[] = [
                            'id' => $variation->get_id(),
                            'price' => $variation->get_price(),
                            'sku' => $variation->get_sku(),
                            'attributes' => $variation->get_variation_attributes(), // Array of variation attributes
                        ];
                    }
                }
        
                // Add variable product and its variations to response
                $response[] = [
                    'id' => $product_obj->get_id(),
                    'name' => $product_obj->get_name(),
                    'price' => $product_obj->get_price(),
                    'type' => 'variable', // Add product type
                    'variations' => $variations, // Include variations
                ];
            } else {
                // Handle other product types (optional)
                $response[] = [
                    'id' => $product_obj->get_id(),
                    'name' => $product_obj->get_name(),
                    'price' => $product_obj->get_price(),
                    'type' => $product_type, // Add product type
                ];
            }
        }
        wp_send_json_success($response);
    } else {
        wp_send_json_error();
    }
}

// Get variations for a product
function get_product_variations_function() {
    if (!isset($_POST['product_id'])) wp_send_json_error();

    $product = wc_get_product($_POST['product_id']);

    wp_send_json_success($product);

    // if (!$product->is_type('variable')) wp_send_json_error();

    $variations = [];
    foreach ($product->get_available_variations() as $variation) {
        $variations[] = [
            'id' => $variation['variation_id'],
            'name' => implode(', ', array_values($variation['attributes'])),
            'price' => $variation['display_price']
        ];
    }

    wp_send_json_success($variations);
}

