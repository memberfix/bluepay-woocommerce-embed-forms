<?php
/**
 * Test page for the renewal form with URL parameter
 * 
 * This file is for testing purposes only and should not be included in production.
 */

// Load WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Check if user is logged in
if (!is_user_logged_in()) {
    auth_redirect();
}

// Load header
get_header();
?>

<div class="container" style="padding: 40px 20px;">
    <h1>Test Renewal Form with URL Parameter</h1>
    
    <div class="test-container">
        <?php echo do_shortcode('[mfx_renewal_form]'); ?>
    </div>
    
    <div class="debug-info" style="margin-top: 30px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd;">
        <h3>Debug Information</h3>
        <p>This section shows debug information to help with development.</p>
        
        <h4>Available Subscriptions</h4>
        <?php
        if (function_exists('wcs_get_users_subscriptions')) {
            $subscriptions = wcs_get_users_subscriptions(get_current_user_id());
            
            if (!empty($subscriptions)) {
                echo '<ul>';
                foreach ($subscriptions as $subscription) {
                    $subscription_id = $subscription->get_id();
                    echo '<li>';
                    echo 'Subscription ID: ' . $subscription_id . '<br>';
                    echo 'Status: ' . $subscription->get_status() . '<br>';
                    
                    // Add test link with subscription ID
                    echo '<a href="' . site_url("/wp-content/plugins/bluepay-woocommerce-embed-forms/test-url-param.php?id={$subscription_id}") . '" class="button" style="margin: 10px 0; display: inline-block; padding: 5px 10px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px;">Test with this subscription</a><br>';
                    
                    // Show subscription items
                    $items = $subscription->get_items();
                    if (!empty($items)) {
                        echo '<strong>Items:</strong><ul>';
                        foreach ($items as $item) {
                            $product_id = $item->get_product_id();
                            $variation_id = $item->get_variation_id();
                            $product = $item->get_product();
                            
                            echo '<li>';
                            echo 'Product ID: ' . $product_id;
                            if ($variation_id) {
                                echo ' (Variation ID: ' . $variation_id . ')';
                            }
                            echo '<br>';
                            
                            if ($product) {
                                echo 'Product Name: ' . $product->get_name() . '<br>';
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    
                    echo '</li><hr>';
                }
                echo '</ul>';
            } else {
                echo '<p>No subscriptions found for current user.</p>';
            }
        } else {
            echo '<p>WooCommerce Subscriptions is not active.</p>';
        }
        ?>
    </div>
</div>

<?php
// Load footer
get_footer();
?>
