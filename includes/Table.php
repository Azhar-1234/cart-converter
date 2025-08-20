<?php 

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SK_Abandoned_Carts_List_Table extends WP_List_Table {

    private $items_data;

    public function __construct() {
        parent::__construct([
            'singular' => 'abandoned_cart',
            'plural'   => 'abandoned_carts',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'products'   => 'Products',
            'user_name'  => 'User Name',
            'email'      => 'Email',
            'phone'      => 'Phone',
            'address'    => 'Address',
            'user_ip'    => 'IP Address',
            'status'     => 'Status',
            'created_at' => 'Created At',
            'actions'    => 'Action',
        ];
    }

    public function column_user_name($item) {
        return esc_html($item->user_name ?? '');
    }

    public function column_email($item) {
        return esc_html($item->email ?? '');
    }

    public function column_phone($item) {
        return esc_html($item->phone ?? '');
    }

    public function column_user_ip($item) {
        return esc_html($item->user_ip ?? '');
    }

    public function column_created_at($item) {
        return esc_html($item->created_at ?? '');
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="cart_ids[]" value="%d" />', $item->id);
    }

    public function column_products($item) {
        $output = '';
        $products = maybe_unserialize($item->products);
        if (is_array($products)) {
            foreach ($products as $product) {
                $output .= esc_html($product['product_name']) . ' (' . esc_html($product['product_id']) . ') x ' . esc_html($product['quantity']) . '<br>';
            }
        }
        return $output;
    }

    public function column_address($item) {
        $address = maybe_unserialize($item->address);
        if (is_array($address)) {
            return esc_html(implode(', ', array_filter($address)));
        }
        return '';
    }

    public function column_status($item) {
        $statuses = ['none', 'confirmed', 'failed'];
        $output = '<form method="post"><input type="hidden" name="cart_id" value="' . esc_attr($item->id) . '">';
        $output .= '<select name="updated_status" onchange="this.form.submit()">';
        foreach ($statuses as $status) {
            $selected = selected($item->updated_status, $status, false);
            $output .= "<option value='$status' $selected>" . ucfirst($status) . "</option>";
        }
        $output .= '</select></form>';
        return $output;
    }

    public function column_actions($item) {
        $edit_url = admin_url('admin.php?page=edit-incomplete-orders&action=edit&id=' . intval($item->id));

        // Add nonce for secure delete URL
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=incomplete-orders&action=delete&id=' . intval($item->id)),
            'delete_cart_' . $item->id
        );

        return sprintf(
            '<a href="%s">Edit</a> | <a href="%s" onclick="return confirm(\'Are you sure you want to delete this cart?\')">Delete</a>',
            esc_url($edit_url),
            esc_url($delete_url)
        );
    }


    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sk_abandoned_carts';

        $columns = $this->get_columns();
        $this->_column_headers = [$columns, [], []];

        // Pagination setup
        $per_page = 50;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Filters
        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($_GET['status'])) {
            $status = sanitize_text_field($_GET['status']);
            if ($status === 'none') {
                $where .= " AND (updated_status = %s OR updated_status IS NULL)";
                $params[] = 'none';
            } else {
                $where .= " AND updated_status = %s";
                $params[] = $status;
            }
        }

        if (!empty($_GET['phone'])) {
            $phone = sanitize_text_field($_GET['phone']);
            $where .= " AND phone LIKE %s";
            $params[] = '%' . $phone . '%';
        }

        // Total count
        $total_sql = "SELECT COUNT(*) FROM $table_name $where";
        $total_items = $wpdb->get_var($wpdb->prepare($total_sql, ...$params));

        // Fetch rows
        $query = "SELECT * FROM $table_name $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $this->items_data = $wpdb->get_results($wpdb->prepare($query, ...$params));
        $this->items = $this->items_data;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}
