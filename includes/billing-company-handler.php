<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('woocommerce_before_order_object_save', 'set_billing_company_to_team_name', 10, 2);
function set_billing_company_to_team_name($order, $data) {
    // Check if the feature is enabled
    $team_name_settings = get_option('mfx_bluepay_team_name_settings', array(
        'enable_team_name_billing' => 1 // Enabled by default
    ));

    if (empty($team_name_settings['enable_team_name_billing'])) {
        error_log("Team name billing company feature is disabled");
        return;
    }

    // Get the user ID from the order (either logged-in user or guest)
    $user_id = $order->get_user_id(); 

    // Check if the user has a team (user ID should exist)
    if ($user_id) {
        // Get current billing company
        $current_billing_company = $order->get_billing_company();
        
        // Only proceed if billing company is empty
        if (empty($current_billing_company)) {
            // Retrieve the team name for the user
            $team_name = mfx_get_team_name_by_user_id($user_id);

            // If a team is found, set it as the billing company for the order
            if ($team_name) {
                error_log("Setting empty billing company to team name: {$team_name} for order #{$order->get_id()}");
                $order->set_billing_company($team_name);
            }

            // Retrieve active subscriptions for the user
            $subscriptions = wcs_get_users_subscriptions($user_id);

            // Loop through each subscription and update the billing company only if empty
            if (!empty($subscriptions)) {
                foreach ($subscriptions as $subscription) {
                    if ($subscription) {
                        $sub_billing_company = $subscription->get_billing_company();
                        if (empty($sub_billing_company) && $team_name) {
                            error_log("Setting empty billing company to team name: {$team_name} for subscription #{$subscription->get_id()}");
                            $subscription->set_billing_company($team_name);
                            $subscription->save();
                        }
                    }
                }
            }
        }
    } else {
        // For guest checkout, you may want to skip or handle differently if needed
        error_log("Guest checkout detected - skipping team name billing company update");
    }
}

// Optional: Custom meta field for tracking the team (if needed for the backend)
add_action('woocommerce_admin_order_data_after_billing_address', 'display_team_name_in_admin', 10, 1);
function display_team_name_in_admin($order) {
    $user_id = $order->get_user_id(); // Get the user ID from the order

    // Check if the user has a team
    if ($user_id) {
        $team_name = mfx_get_team_name_by_user_id($user_id);

        if ($team_name) {
            echo '<p><strong>Company Name:</strong> ' . esc_html($team_name) . '</p>';
        }
    }
}