<?php

/**
 * DISPLAY Incomplete Orders and Filters
 */

if (! sk_is_license_active()) {
    return;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
if ( ! function_exists( 'wp_redirect' ) ) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

include 'Table.php';

 global $wpdb;
 $table_name = $wpdb->prefix . 'sk_abandoned_carts';
 
// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $cart_id = intval($_GET['id']);
    $wpdb->delete($table_name, ['id' => $cart_id]);

    // Redirect to avoid duplicate deletions
    wp_redirect(admin_url('admin.php?page=incomplete-orders'));
    exit;
}

add_action('wp_ajax_download_abandoned_carts', 'download_abandoned_carts');
function download_abandoned_carts(){
    if (isset($_POST['download_csv'])) {
        // Generate CSV download
        export_abandoned_carts_csv();
    }
}

function export_abandoned_carts_csv(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sk_abandoned_carts';
    // Fetch abandoned carts
    $abandoned_carts = $wpdb->get_results("SELECT * FROM $table_name");

    // Output headers for CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=cart_converter.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Products', 'User Name', 'Email', 'Phone', 'Address', 'IP Address', 'Status', 'Created At']);

    foreach ($abandoned_carts as $cart) {
        $products = unserialize($cart->products);
        if (empty($products) || !is_array($products)) {
            continue; // Skip if unserialized value is not an array or empty
        }
        $product_names = [];
        foreach ($products as $product) {
            $product_names[] = $product['product_name'];
        }
        // 1. Unserialize it
        $address_data = unserialize($cart->address);
        if (is_array($address_data)) {
            $formatted_address = implode(', ', array_filter($address_data));
        }
        fputcsv($output, [implode(', ', $product_names), $cart->user_name, $cart->email, $cart->phone, $formatted_address, $cart->user_ip, $cart->updated_status, $cart->created_at]);
    }

    fclose($output);
    exit;
}

add_action('wp_ajax_fetch_overall_report_json', 'fetch_overall_report_json');
function fetch_overall_report_json() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sk_abandoned_carts';

    $results = $wpdb->get_results(
        "SELECT COALESCE(updated_status, 'none') AS status, COUNT(*) AS count FROM $table_name GROUP BY status",
        OBJECT_K
    );

    $confirmed = $results['confirmed']->count ?? 0;
    $failed = $results['failed']->count ?? 0;
    $none = $results['none']->count ?? 0;

    $total = $confirmed + $failed + $none;
    $confirm_ratio = $total ? number_format(($confirmed / $total) * 100, 2) : '0.00';

    wp_send_json(compact('confirmed', 'failed', 'none', 'confirm_ratio'));
}


// Function for filtering and displaying the abandoned carts
function act_display_abandoned_carts()
{

    global $wpdb;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'], $_POST['updated_status'])) {
        $table_name = $wpdb->prefix . 'sk_abandoned_carts';

        $cart_id = intval($_POST['cart_id']);
        $status = sanitize_text_field($_POST['updated_status']);

        $wpdb->update(
            $table_name,
            ['updated_status' => $status],
            ['id' => $cart_id],
            ['%s'],
            ['%d']
        );

        // Optional: Prevent resubmission on refresh
        wp_redirect(remove_query_arg(['updated_status', 'cart_id']));
        exit;
    }
    
    echo '<div class="wrap">';
    echo '<div style="display:flex;gap:12px;align-items:center; margin: 20px 0px"><h1 style="padding:0px">Incomplete Orders</h1>';
    echo '<button id="view-report-btn" class="button button-primary">View Report <span class="dashicons dashicons-chart-bar" style="margin:3px 0 0 4px"></span></button>';
    // Download options
    echo '<form method="post" action="' . esc_url(admin_url('admin-ajax.php')) . '" class="sk-incomplete-orders-download">
            <input type="hidden" name="action" value="download_abandoned_carts">
            <button type="submit" name="download_csv" value="1" class="button button-primary">Download CSV <span class="dashicons dashicons-printer" style="margin-top:4px"></span></button>
          </form>';
    echo '</div>';

    echo '<div id="report-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999;">
            <div style="background:#fff; padding:20px; width:600px; max-width:90%; margin:100px auto; position:relative; border-radius:8px;">
                <h2>Overall Report</h2>
                <div class="s-ratio" id="s-ratio"></div>
                <canvas id="reportChart" width="400" height="400"></canvas>
                <button id="close-report-btn" class="button" style="margin-top:15px;">Close</button>
            </div>
        </div>';

    echo ' <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const modal = document.getElementById("report-modal");
                const openBtn = document.getElementById("view-report-btn");
                const closeBtn = document.getElementById("close-report-btn");
                let chartInstance = null;

                console.log("openBtn",openBtn);

                if(!openBtn || !closeBtn) return;
                openBtn.addEventListener("click", function () {
                    modal.style.display = "block";

                    fetch("' . admin_url('admin-ajax.php?action=fetch_overall_report_json') . '")
                        .then(res => res.json())
                        .then(data => {
                            document.getElementById("s-ratio").innerHTML = "Success Ratio: " + data.confirmed + "%";
                            const ctx = document.getElementById("reportChart").getContext("2d");

                            if (chartInstance) {
                                chartInstance.destroy();
                            }

                            chartInstance = new Chart(ctx, {
                                type: "pie",
                                data: {
                                    labels: ["Confirmed", "Failed", "None/Unspecified"],
                                    datasets: [{
                                        label: "Order Status",
                                        data: [data.confirmed, data.failed, data.none],
                                        backgroundColor: [
                                            "rgba(75, 192, 192, 0.7)",  // confirmed
                                            "rgba(255, 99, 132, 0.7)",  // failed
                                            "rgba(201, 203, 207, 0.7)"  // none
                                        ],
                                        borderColor: [
                                            "rgba(75, 192, 192, 1)",
                                            "rgba(255, 99, 132, 1)",
                                            "rgba(201, 203, 207, 1)"
                                        ],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true
                                }
                            });
                        })
                        .catch(err => {
                            alert("Failed to load chart data.");
                        });
                });

                closeBtn.addEventListener("click", function () {
                    modal.style.display = "none";
                });

                window.addEventListener("click", function (e) {
                    if (e.target == modal) {
                        modal.style.display = "none";
                    }
                });
            });
        </script>';

    echo '<form method="get" action="" style="margin-bottom: 16px; display: flex; align-items: center; gap: 2px;">';
    echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
    echo '<select name="status">
            <option value="">All Status</option>
            <option value="confirmed" ' . selected($_GET['status'] ?? '', 'confirmed', false) . '>Confirmed</option>
            <option value="failed" ' . selected($_GET['status'] ?? '', 'failed', false) . '>Failed</option>
            <option value="none" ' . selected($_GET['status'] ?? '', 'none', false) . '>None</option>
        </select>';

    echo '<input type="text" name="phone" placeholder="Search by phone" value="' . esc_attr($_GET['phone'] ?? '') . '" />';
    echo '<button type="submit" class="button button-primary">Filter</button>';
    echo '<a href="' . admin_url('admin.php?page=' . esc_attr($_GET['page'])) . '" class="button">Reset</a>';
    echo '</form><br>';

    $table = new SK_Abandoned_Carts_List_Table();
    $table->prepare_items();
    $table->display();

echo '</div>';


}
