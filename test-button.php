<?php
/**
 * Test page for the "Change My Membership" button
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
    <h1>Test "Change My Membership" Buttons</h1>
    
    <p>This page simulates the "Change My Membership" buttons that would appear in the user's account page.</p>
    
    <div class="test-buttons" style="margin-top: 20px;">
        <h3>Your Subscriptions</h3>
        
        <?php
        if (function_exists('wcs_get_users_subscriptions')) {
            $subscriptions = wcs_get_users_subscriptions(get_current_user_id());
            
            if (!empty($subscriptions)) {
                echo '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
                echo '<thead>';
                echo '<tr>';
                echo '<th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Subscription</th>';
                echo '<th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Status</th>';
                echo '<th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Actions</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($subscriptions as $subscription) {
                    $subscription_id = $subscription->get_id();
                    
                    echo '<tr>';
                    echo '<td style="padding: 10px; border-bottom: 1px solid #eee;">#' . esc_html($subscription_id) . '</td>';
                    echo '<td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html(wcs_get_subscription_status_name($subscription->get_status())) . '</td>';
                    echo '<td style="padding: 10px; border-bottom: 1px solid #eee;">';
                    
                    // Add the "Change My Membership" button
                    echo '<a href="' . esc_url(site_url("/change-my-membership?id={$subscription_id}")) . '" class="button" style="display: inline-block; padding: 8px 12px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px;">Change My Membership</a>';
                    
                    // Add a link to the test page with the ID parameter
                    echo ' <a href="' . esc_url(site_url("/wp-content/plugins/bluepay-woocommerce-embed-forms/test-url-param.php?id={$subscription_id}")) . '" class="button" style="display: inline-block; padding: 8px 12px; background: #555; color: white; text-decoration: none; border-radius: 3px; margin-left: 10px;">Test Page</a>';
                    
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p>No subscriptions found for current user.</p>';
            }
        } else {
            echo '<p>WooCommerce Subscriptions is not active.</p>';
        }
        ?>
    </div>
    
    <div class="note" style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-left: 4px solid #2271b1;">
        <p><strong>Note:</strong> In a real implementation, the "Change My Membership" button would lead to the page where you've added the <code>[mfx_renewal_form]</code> shortcode.</p>
        <p>Make sure you've created a page with the slug "change-my-membership" that contains the shortcode.</p>
    </div>
</div>

<?php
// Load footer
get_footer();
?>
