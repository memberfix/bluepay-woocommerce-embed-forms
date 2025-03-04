<?php
/**
 * Test page for the plan update functionality
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
    echo '<h1>Test Plan Update Functionality</h1>';
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
    echo '<h1>Test Plan Update Functionality</h1>';
    echo '<p>You do not have permission to access this subscription.</p>';
    echo '</div>';
    get_footer();
    exit;
}

// Get the subscription object
$subscription = wcs_get_subscription($subscription_id);

// Get renewal settings for product IDs
$renewal_settings = get_option('mfx_bluepay_renewal_settings', array(
    'membership_product_id' => 12350,
    'premium_service_product_id' => 12390,
    'local_chapter_product_id' => 12428,
    'supplier_product_id' => 12386
));

// Get product variations for Type A and Type D
$type_a_variations = array();
$type_d_variations = array();

// Get Type A (membership) variations
$type_a_parent_id = $renewal_settings['membership_product_id'];
$type_a_product = wc_get_product($type_a_parent_id);
if ($type_a_product && $type_a_product->is_type('variable')) {
    $type_a_variations = $type_a_product->get_available_variations();
}

// Get Type D (supplier) variations
$type_d_parent_id = $renewal_settings['supplier_product_id'];
$type_d_product = wc_get_product($type_d_parent_id);
if ($type_d_product && $type_d_product->is_type('variable')) {
    $type_d_variations = $type_d_product->get_available_variations();
}

// Process form submission
$message = '';
$status = '';

if (isset($_POST['update_plan'])) {
    $selected_variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
    $selected_plan = isset($_POST['plan']) ? sanitize_text_field($_POST['plan']) : '';
    
    if ($selected_variation_id && $selected_plan) {
        // Include necessary files
        if (!function_exists('mfx_process_subscription_update')) {
            require_once plugin_dir_path(__FILE__) . 'includes/subscription-update.php';
        }
        
        // Set up the POST data
        $_POST['subscription_id'] = $subscription_id;
        $_POST['variations'] = array($selected_variation_id);
        $_POST['selected_plan'] = $selected_plan;
        $_POST['nonce'] = wp_create_nonce('mfx_renewal_form_nonce');
        
        // Call the update function
        ob_start();
        mfx_process_subscription_update();
        ob_end_clean();
        
        $message = 'Subscription plan update attempted. Check the error logs for details.';
        $status = 'success';
        
        // Refresh the subscription object
        $subscription = wcs_get_subscription($subscription_id);
    } else {
        $message = 'Please select a variation and plan.';
        $status = 'error';
    }
}
?>

<div class="container" style="padding: 40px 20px;">
    <h1>Test Plan Update Functionality</h1>
    
    <?php if ($message): ?>
    <div class="message <?php echo esc_attr($status); ?>" style="margin-bottom: 20px; padding: 15px; background: <?php echo $status === 'success' ? '#dff0d8' : '#f2dede'; ?>; border: 1px solid <?php echo $status === 'success' ? '#d6e9c6' : '#ebccd1'; ?>; border-radius: 4px;">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="subscription-info" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px;">
        <h3>Subscription Information</h3>
        <p>ID: <?php echo esc_html($subscription_id); ?></p>
        <p>Status: <?php echo esc_html(wcs_get_subscription_status_name($subscription->get_status())); ?></p>
        <p>Billing Period: <?php echo esc_html($subscription->get_billing_period()); ?></p>
        <p>Billing Interval: <?php echo esc_html($subscription->get_billing_interval()); ?></p>
        <p>Next Payment: <?php echo $subscription->get_date('next_payment') ? esc_html($subscription->get_date('next_payment')->date('Y-m-d H:i:s')) : 'N/A'; ?></p>
    </div>
    
    <div class="test-form" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px;">
        <h3>Test Plan Update</h3>
        <form method="post" action="">
            <div style="margin-bottom: 15px;">
                <label for="variation_id" style="display: block; margin-bottom: 5px; font-weight: bold;">Select Variation:</label>
                <select name="variation_id" id="variation_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">-- Select Variation --</option>
                    
                    <?php if (!empty($type_a_variations)): ?>
                        <optgroup label="Type A (Membership) Variations">
                            <?php foreach ($type_a_variations as $variation): ?>
                                <?php 
                                $variation_obj = wc_get_product($variation['variation_id']);
                                $plan = $variation_obj->get_attribute('plan');
                                if (!empty($plan)):
                                ?>
                                <option value="<?php echo esc_attr($variation['variation_id']); ?>">
                                    <?php echo esc_html($variation['variation_id'] . ' - ' . $variation_obj->get_name() . ' (Plan: ' . $plan . ')'); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    
                    <?php if (!empty($type_d_variations)): ?>
                        <optgroup label="Type D (Supplier) Variations">
                            <?php foreach ($type_d_variations as $variation): ?>
                                <?php 
                                $variation_obj = wc_get_product($variation['variation_id']);
                                $annual_revenue = $variation_obj->get_attribute('annual-revenue');
                                ?>
                                <option value="<?php echo esc_attr($variation['variation_id']); ?>">
                                    <?php echo esc_html($variation['variation_id'] . ' - ' . $variation_obj->get_name() . ' (Default Plan: annual)'); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label for="plan" style="display: block; margin-bottom: 5px; font-weight: bold;">Select Plan:</label>
                <select name="plan" id="plan" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">-- Select Plan --</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="annual">Annual</option>
                </select>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" name="update_plan" class="button" style="display: inline-block; padding: 10px 15px; background: #2271b1; color: white; text-decoration: none; border: none; border-radius: 3px; cursor: pointer;">Update Plan</button>
                <a href="<?php echo esc_url(wc_get_endpoint_url('view-subscription', $subscription_id, wc_get_page_permalink('myaccount'))); ?>" class="button" style="display: inline-block; padding: 10px 15px; background: #f0f0f0; color: #333; text-decoration: none; border: 1px solid #ddd; border-radius: 3px; margin-left: 10px;">Back to Subscription</a>
            </div>
        </form>
    </div>
    
    <div class="note" style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-left: 4px solid #2271b1;">
        <p><strong>Note:</strong> This page is for testing the plan update functionality. It allows you to directly select a variation and plan to update the subscription.</p>
        <p>In the actual implementation:</p>
        <ul style="margin-left: 20px;">
            <li>For Type A (Membership) products: The plan will be extracted from the selected variation's 'plan' attribute.</li>
            <li>For Type D (Supplier) products: The plan will default to 'annual' since these products don't have a plan attribute.</li>
        </ul>
    </div>
</div>

<?php
// Load footer
get_footer();
?>
