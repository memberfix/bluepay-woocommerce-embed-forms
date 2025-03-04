<?php
/**
 * Test page for the redirect functionality
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

// Get subscription ID from URL parameter
$subscription_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

// If no subscription ID provided, show error message
if (!$subscription_id) {
    echo '<div class="container" style="padding: 40px 20px;">';
    echo '<h1>Test Redirect Functionality</h1>';
    echo '<p>No subscription ID provided. Please use the URL parameter "id".</p>';
    echo '</div>';
    get_footer();
    exit;
}

// Verify that the subscription belongs to the current user
$user_subscriptions = wcs_get_users_subscriptions(get_current_user_id());
$subscription_exists = false;

foreach ($user_subscriptions as $user_subscription) {
    if ($user_subscription->get_id() == $subscription_id) {
        $subscription_exists = true;
        break;
    }
}

if (!$subscription_exists) {
    echo '<div class="container" style="padding: 40px 20px;">';
    echo '<h1>Test Redirect Functionality</h1>';
    echo '<p>You do not have permission to access this subscription.</p>';
    echo '</div>';
    get_footer();
    exit;
}

// Get the subscription object
$subscription = wcs_get_subscription($subscription_id);
?>

<div class="container" style="padding: 40px 20px;">
    <h1>Test Redirect Functionality</h1>
    
    <div class="subscription-info" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px;">
        <h3>Subscription Information</h3>
        <p>ID: <?php echo esc_html($subscription_id); ?></p>
        <p>Status: <?php echo esc_html(wcs_get_subscription_status_name($subscription->get_status())); ?></p>
    </div>
    
    <div class="redirect-test" style="margin-top: 20px;">
        <h3>Redirect URL</h3>
        <?php
        // Generate the redirect URL
        $redirect_url = wc_get_endpoint_url('view-subscription', $subscription_id, wc_get_page_permalink('myaccount')) . '?status_update=success';
        ?>
        <p>The redirect URL will be: <code><?php echo esc_html($redirect_url); ?></code></p>
        
        <div style="margin-top: 20px;">
            <a href="<?php echo esc_url($redirect_url); ?>" class="button" style="display: inline-block; padding: 10px 15px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px;">Test Redirect</a>
        </div>
    </div>
    
    <div class="note" style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-left: 4px solid #2271b1;">
        <p><strong>Note:</strong> This page is for testing the redirect functionality. In the actual implementation, the redirect will happen automatically after the membership is updated.</p>
    </div>
</div>

<?php
// Load footer
get_footer();
?>
