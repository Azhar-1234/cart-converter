<?php 
/**
* EDIT ITEM
*/

if( ! sk_is_license_active() ){
    return ;
}

function act_edit_abandoned_cart() {

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        wp_die('Invalid cart ID.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sk_abandoned_carts';
    $cart_id = intval($_GET['id']);
    
    $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id));

    if (!$cart) {
        wp_die('Cart not found.');
    }

    $products = unserialize($cart->products);
    $address = unserialize($cart->address);

    // UPDATE CALL

    if (isset($_POST['update_cart'])) {
        global $wpdb;
        $cart_id = intval($_POST['cart_id']);

        // Sanitize and prepare general cart details
        $data = array(
            'user_name'        => sanitize_text_field($_POST['first_name']) . ' ' .sanitize_text_field($_POST['last_name']),
            'first_name'       => sanitize_text_field($_POST['first_name']),
            'last_name'        => sanitize_text_field($_POST['last_name']),
            'email'            => sanitize_email($_POST['email']),
            'phone'            => sanitize_text_field($_POST['phone']),
            'address'          => maybe_serialize(explode(',', sanitize_textarea_field($_POST['address']))),
            'additional_text'  => sanitize_textarea_field($_POST['additional_text']),
            'checkout_method'  => sanitize_text_field($_POST['checkout_method'])
        );

        // Handle product updates
        if (!empty($_POST['productss'])) {
            
            $products = $_POST['productss'];
            $fixed_products = [];
            $temp_product = [];

            foreach ($products as $product) {
                foreach ($product as $key => $value) {
                    $temp_product[$key] = $value;

                    // When we have all fields for a single product, push it into the array
                    if (count($temp_product) == 4) {
                        $fixed_products[] = $temp_product;
                        $temp_product = []; // Reset for the next product
                    }
                }
            }

            $data['products'] = maybe_serialize($fixed_products);
        }

        // Update the database
        $where = array('id' => $cart_id);
        $updated = $wpdb->update($table_name, $data, $where);

        if ($updated !== false) {
            // wp_redirect(admin_url('admin.php?page=edit-abandoned-cart&action=edit&id='.$cart_id));
            echo '<script>window.location.reload();</script>';
            exit;
        } else {
            echo '<div class="error"><p>Failed to update cart.</p></div>';
        }
    }

    
    echo '<div class="wrap">';
    echo '<h1>Edit Abandoned Cart</h1>';
    
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="cart_id" value="' . esc_attr($cart->id) . '">';

    echo '<table class="form-table">';

    echo '<tr><th>First Name:</th><td><input type="text" name="first_name" value="' . esc_attr($cart->first_name) . '" class="regular-text"></td></tr>';
    echo '<tr><th>Last Name:</th><td><input type="text" name="last_name" value="' . esc_attr($cart->last_name) . '" class="regular-text"></td></tr>';
    echo '<tr><th>Email:</th><td><input type="email" name="email" value="' . esc_attr($cart->email) . '" class="regular-text"></td></tr>';
    echo '<tr><th>Phone:</th><td><input type="text" name="phone" value="' . esc_attr($cart->phone) . '" class="regular-text"></td></tr>';
    echo '<tr><th>Address:</th><td><textarea name="address" class="regular-text">' . esc_textarea(implode(', ', $address)) . '</textarea></td></tr>';
    echo '<tr><th>Additional Notes:</th><td><textarea name="additional_text" class="regular-text">' . esc_textarea($cart->additional_text) . '</textarea></td></tr>';
    echo '<tr><th>Checkout Method:</th><td><input type="text" name="checkout_method" value="' . esc_attr($cart->checkout_method) . '" class="regular-text"></td></tr>';

    echo '</table>';


    // ADD PRODUCT SECTION

    echo '<h2>Search & Add Product</h2>';
    echo '<input type="text" id="product_search" class="regular-text" placeholder="Search products...">';
    echo '<select id="product_variations" style="display:none;"></select>';
    echo '<button type="button" id="sk_add_product" class="button button-primary">Add Product</button>';
    echo '<div id="search_results"></div>';


    // PRODUCT SHOWING TABLE

    echo '<h2>Product Details</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Price</th><th>Quantity</th><th>Action</th></tr></thead>';
    echo '<tbody>';

    foreach ($products as $index => $product) {
        echo '<tr id="product-row-' . esc_attr($product['product_id']) . '">';
        
        // ID Column
        echo '<td>';
        echo esc_html($product['product_id']);
        echo '<input type="hidden" name="productss[][product_id]" value="' . esc_attr($product['product_id']) . '">';
        echo '</td>';
        
        // Name Column
        echo '<td>';
        echo esc_html($product['product_name']);
        echo '<input type="hidden" name="productss[][product_name]" value="' . esc_attr($product['product_name']) . '">';
        echo '</td>';
        
        // Price Column
        echo '<td>';
        echo esc_html($product['price']);
        echo '<input type="hidden" name="productss[][price]" value="' . esc_attr($product['price']) . '">';
        echo '</td>';
        
        // Quantity Column
        echo '<td>';
        //echo esc_html($product['quantity']);
        echo '<input type="number" name="productss[][quantity]" value="' . esc_attr($product['quantity']) . '">';
        echo '</td>';

        // Action for delete any items from products
	    echo '<td><a href="#" class="sk-delete-product" data-product-id="' . esc_attr($product['product_id']) . '">Delete</a></td>';
        
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<br><br>';
    echo '<button type="button" id="sk_redirect_to_checkout" class="button button-primary">Create Order</button> ';
    echo '<button type="submit" name="update_cart" class="button button-primary">Save Changes</button> ';
    echo '<a href="admin.php?page=incomplete-orders" class="button">Cancel</a>';
    echo '</form>';
    echo '</div>';
}

?>
