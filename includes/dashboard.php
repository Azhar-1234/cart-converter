<?php
/**
 * Dashboard
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$is_authorized = false;
if (isset($_POST['sk_license_submit']) && !empty($_POST['sk_license_key_cc'])) {
    $is_authorized = sk_license_check_manager(sanitize_text_field($_POST['sk_license_key_cc']));
    if ($is_authorized) {
        update_option('sk_license_key_cc', sanitize_text_field($_POST['sk_license_key_cc']));
    }
    wp_safe_redirect(admin_url('admin.php?page=cart-converter-dashboard'));
    exit();
} else {
    $is_authorized = sk_is_license_active();
}

$status_class = $is_authorized ? '_authorized' : 'not_authorized';
$status_img   = $is_authorized 
    ? 'https://cdn-icons-png.flaticon.com/512/2722/2722007.png'
    : 'https://cdn-icons-png.flaticon.com/512/6711/6711603.png';

$products = [
    [
        'img' => 'https://i0.wp.com/servicekey.io/wp-content/uploads/2024/10/11132-scaled.jpg?fit=2560%2C1707&ssl=1',
        'title' => 'Google Spreadsheet Integration',
        'desc' => 'Our Google Spreadsheet Integration plugin connects your website with Google Sheets to enable automated data transfer and real-time updates. Easily manage inventory, track orders, and collaborate on projects while keeping your spreadsheets accurate and up-to-date. Save time and simplify your workflow effortlessly.',
        'url' => 'https://servicekey.com.bd/google-spreadsheet-integration'
    ],
    [
        'img' => 'https://i0.wp.com/servicekey.io/wp-content/uploads/2024/10/11132-scaled.jpg?fit=2560%2C1707&ssl=1',
        'title' => 'Fake Order Tracker',
        'desc' => 'The Fake Order Tracker plugin secures your online store by identifying and blocking fraudulent or suspicious orders. With real-time monitoring and advanced algorithms, it helps you maintain the integrity of your order process. Say goodbye to wasting resources on fake orders and focus on genuine customers confidently and easily.',
        'url' => 'https://servicekey.com.bd/fake-order-tracker/'
    ],
    [
        'img' => 'https://i0.wp.com/servicekey.io/wp-content/uploads/2024/10/11132-scaled.jpg?fit=2560%2C1707&ssl=1',
        'title' => 'Cart Converter',
        'desc' => 'Our Cart Converter plugin shows about 60% to 80% of the users who go to the checkout page, do not complete their purchase. Through the email series, you can: remind them to complete the purchase, ask for feedback or offer a custom discount that will entice potential buyers to complete the purchase. You can send as many emails as you would like.',
        'url' => 'https://servicekey.com.bd/cart-converter-plugin/'
    ]
];
?>
<div class="sk_dashboard_wrapper">
    <div class="sk_dashboard_container">
        <div class="sk_dashboard_grid">
            <div class="sk_grid_col <?php echo esc_attr($status_class); ?>">
                <div class="sk_welcome_note">
                    <?php if (!$is_authorized): ?>
                        <h1>Plugin Activation Pending!</h1>
                        <p>The Service Key Cart Converter Plugin is not yet activated.</p>
                        <p>Contact our support team to obtain your license key and unlock the full suite of order-tracking features.</p>
                        <form method="POST">
                            <input type="text" name="sk_license_key_cc" class="sk_license-field" placeholder="Enter your license key" />
                            <input type="submit" name="sk_license_submit" class="sk_license-submit" value="Activate" />
                        </form>
                    <?php else: ?>
                        <h6>Congratulations!</h6>
                        <h1>Your Cart Converter Plugin is now active!</h1>
                        <p>Set up your settings to unlock the full range of features and maximize your store's protection against fake orders.</p>
                        <p>To ensure fast and reliable website performance, our tools use the best practices to deliver immediate benefits to your site.</p>
                    <?php endif; ?>
                </div>
                <div class="sk_plugin_status">
                    <img src="<?php echo esc_url($status_img); ?>" alt="Plugin Status" />
                </div>
            </div>
            <div class="sk_grid_col">
                <div class="sk_other_products">
                    <?php foreach ($products as $product): ?>
                        <div class="sk_product_item">
                            <img src="<?php echo esc_url($product['img']); ?>" alt="<?php echo esc_attr($product['title']); ?>" />
                            <div class="sk_product_content">
                                <h3><?php echo esc_html($product['title']); ?></h3>
                                <p><?php echo esc_html($product['desc']); ?></p>
                                <a href="<?php echo esc_url($product['url']); ?>">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>