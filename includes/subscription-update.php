<?php 

function mfx_process_subscription_update() {
    check_ajax_referer('subscription_update_nonce', 'nonce');
    
    $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
    $selected_variations = isset($_POST['selected_variations']) ? $_POST['selected_variations'] : array();
    $selected_plan = isset($_POST['selected_plan']) ? sanitize_text_field($_POST['selected_plan']) : '';
    
    if (!$subscription_id || empty($selected_variations)) {
        error_log('Invalid subscription data - ID: ' . $subscription_id);
        wp_send_json_error(array(
            'message' => 'Invalid membership data',
            'code' => 'invalid_data'
        ));
        return;
    }

    $subscription = wcs_get_subscription($subscription_id);
    if (!$subscription) {
        error_log('Subscription not found - ID: ' . $subscription_id);
        wp_send_json_error(array(
            'message' => 'Membership not found',
            'code' => 'subscription_not_found'
        ));
        return;
    }

    // Verify user has permission to modify this subscription
    if (!current_user_can('edit_shop_subscription', $subscription_id) && $subscription->get_user_id() != get_current_user_id()) {
        error_log('User does not have permission to modify subscription - ID: ' . $subscription_id);
        wp_send_json_error(array(
            'message' => 'You do not have permission to modify this membership',
            'code' => 'permission_denied'
        ));
        return;
    }

    // Validate subscription status
    if (!in_array($subscription->get_status(), array('active', 'on-hold', 'pending'))) {
        error_log('Invalid subscription status for update - ID: ' . $subscription_id . ', Status: ' . $subscription->get_status());
        wp_send_json_error(array(
            'message' => 'Membership cannot be updated in its current status',
            'code' => 'invalid_status'
        ));
        return;
    }

    // Get team metadata from subscription's current items
    $team_data = array();
    $allowed_parent_product_ids = array(12350, 12386);

    foreach ($subscription->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        error_log("Checking subscription item - Product ID: $product_id");

        // Check if the product is in the allowed list
        if (!in_array($product_id, $allowed_parent_product_ids)) {
            error_log("Product ID $product_id not in allowed list");
            continue;
        }

        // Get metadata from the subscription item
        $team_id = wc_get_order_item_meta($item_id, '_wc_memberships_for_teams_team_id', true);
        $team_name = wc_get_order_item_meta($item_id, 'team_name', true);

        error_log("Found metadata in subscription item $item_id - Team ID: $team_id, Team Name: $team_name");

        if ($team_id && $team_name) {
            $team_data = array(
                'team_id' => $team_id,
                'team_name' => $team_name
            );
            error_log("Using team data - Team ID: $team_id, Team Name: $team_name");
            break;
        }
    }

    if (empty($team_data)) {
        error_log('No team data found in subscription items, checking parent order...');
        
        // If no team data in subscription items, check parent order
        $parent_order_id = $subscription->get_parent_id();
        if ($parent_order_id) {
            $parent_order = wc_get_order($parent_order_id);
            if ($parent_order) {
                foreach ($parent_order->get_items() as $parent_item_id => $parent_item) {
                    $product_id = $parent_item->get_product_id();
                    error_log("Checking parent order item - Product ID: $product_id");

                    // Check if the product is in the allowed list
                    if (!in_array($product_id, $allowed_parent_product_ids)) {
                        error_log("Product ID $product_id not in allowed list");
                        continue;
                    }

                    // Get metadata from the parent order item
                    $team_id = wc_get_order_item_meta($parent_item_id, '_wc_memberships_for_teams_team_id', true);
                    $team_name = wc_get_order_item_meta($parent_item_id, 'team_name', true);

                    error_log("Found metadata in parent order item $parent_item_id - Team ID: $team_id, Team Name: $team_name");

                    if ($team_id && $team_name) {
                        $team_data = array(
                            'team_id' => $team_id,
                            'team_name' => $team_name
                        );
                        error_log("Using team data from parent order - Team ID: $team_id, Team Name: $team_name");
                        break;
                    }
                }
            }
        }
    }

    if (empty($team_data)) {
        error_log('No team data found in subscription items or parent order');
        wp_send_json_error(array(
            'message' => 'No team data found in subscription items or parent order',
            'code' => 'no_team_data'
        ));
        return;
    }
    
    // Update subscription items
    try {
        $update_items_result = mfx_update_subscription_items($subscription, $selected_variations, $team_data);
        if (is_wp_error($update_items_result)) {
            throw new Exception($update_items_result->get_error_message());
        }

        // Update subscription plan if specified
        if (!empty($selected_plan)) {
            $update_plan_result = mfx_update_subscription_recurring_period($subscription, $selected_plan);
            if (is_wp_error($update_plan_result)) {
                throw new Exception($update_plan_result->get_error_message());
            }
        }

        // Save all changes
        $subscription->save();
        
        // Get the redirect URL
        $redirect_url = wc_get_account_endpoint_url('mfx-membership');
        error_log('Redirect URL for membership update: ' . $redirect_url);
        
        wp_send_json_success(array(
            'message' => 'Membership updated successfully',
            'subscription_id' => $subscription_id,
            'redirect' => $redirect_url
        ));
        
    } catch (Exception $e) {
        error_log('Failed to update subscription: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => $e->getMessage(),
            'code' => 'update_failed'
        ));
        return;
    }

    // Handle pending/failed orders
    $last_order = $subscription->get_last_order('all');
    if ($last_order) {
        $order_status = $last_order->get_status();
        if ($order_status === 'pending') {
            $update_pending_result = mfx_update_pending_order_items($last_order, $selected_variations, $team_data);
            if (is_wp_error($update_pending_result)) {
                error_log('Failed to update pending order: ' . $update_pending_result->get_error_message());
                wp_send_json_error(array(
                    'message' => $update_pending_result->get_error_message(),
                    'code' => 'update_pending_failed'
                ));
                return;
            }
        } elseif ($order_status === 'failed') {
            $update_failed_result = mfx_update_failed_order_items($last_order, $selected_variations, $team_data);
            if (is_wp_error($update_failed_result)) {
                error_log('Failed to update failed order: ' . $update_failed_result->get_error_message());
                wp_send_json_error(array(
                    'message' => $update_failed_result->get_error_message(),
                    'code' => 'update_failed_failed'
                ));
                return;
            }
        }
    }
    
    error_log('Subscription ' . $subscription_id . ' updated successfully');
}

function mfx_update_subscription_items($subscription, $selected_variations, $team_data) {
    try {
        error_log("Starting to update subscription items with team data - Team ID: {$team_data['team_id']}, Team Name: {$team_data['team_name']}");
        
        // Remove existing items
        foreach ($subscription->get_items() as $item_id => $item) {
            $subscription->remove_item($item_id);
            error_log("Removed subscription item: $item_id");
        }
        
        // Add new items
        foreach ($selected_variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $item_id = $subscription->add_product($variation);
                if ($item_id) {
                    // Update team metadata using WooCommerce's functions
                    wc_update_order_item_meta($item_id, '_wc_memberships_for_teams_team_id', $team_data['team_id']);
                    wc_update_order_item_meta($item_id, 'team_name', $team_data['team_name']);
                    error_log("Updated subscription item $item_id with team data - Team ID: {$team_data['team_id']}, Team Name: {$team_data['team_name']}");
                }
            }
        }
        $subscription->calculate_totals();
        $subscription->save();
        
        return true;
    } catch (Exception $e) {
        error_log('Error updating subscription items: ' . $e->getMessage());
        return new WP_Error('update_error', $e->getMessage());
    }
}

function mfx_update_pending_order_items($order, $selected_variations, $team_data) {
    try {
        error_log("Starting to update pending order items with team data");
        
        // Remove existing items
        foreach ($order->get_items() as $item_id => $item) {
            $order->remove_item($item_id);
            error_log("Removed order item: $item_id");
        }
        
        // Add new items
        foreach ($selected_variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $item_id = $order->add_product($variation);
                if ($item_id) {
                    // Update team metadata using WooCommerce's functions
                    wc_update_order_item_meta($item_id, '_wc_memberships_for_teams_team_id', $team_data['team_id']);
                    wc_update_order_item_meta($item_id, 'team_name', $team_data['team_name']);
                    error_log("Updated pending order item $item_id with team data");
                }
            }
        }
        $order->calculate_totals();
        $order->save();
        
        return true;
    } catch (Exception $e) {
        error_log('Error updating pending order items: ' . $e->getMessage());
        return new WP_Error('update_error', $e->getMessage());
    }
}

function mfx_update_failed_order_items($order, $selected_variations, $team_data) {
    try {
        error_log("Starting to update failed order items with team data");
        return mfx_update_pending_order_items($order, $selected_variations, $team_data);
    } catch (Exception $e) {
        error_log('Error updating failed order items: ' . $e->getMessage());
        return new WP_Error('update_error', $e->getMessage());
    }
}

function mfx_update_subscription_recurring_period($subscription, $selected_plan) {
    try {
        //error_log("Starting subscription period update with plan: " . print_r($selected_plan, true));
        
        // Convert plan to lowercase and trim for consistency
        $selected_plan = strtolower(trim($selected_plan));
        
        $period_mapping = array(
            'monthly' => array('interval' => 1, 'period' => 'month'),
            'quarterly' => array('interval' => 3, 'period' => 'month'),
            'annual' => array('interval' => 1, 'period' => 'year')
        );
        
        if (!isset($period_mapping[$selected_plan])) {
            $error_message = "Invalid subscription plan selected: '$selected_plan'. Available plans: " . implode(', ', array_keys($period_mapping));
            error_log($error_message);
            return new WP_Error('invalid_plan', $error_message);
        }
        
        $new_interval = $period_mapping[$selected_plan]['interval'];
        $new_period = $period_mapping[$selected_plan]['period'];
        
        error_log("Setting new interval to: $new_interval and period to: $new_period");
        
        try {
            // Get all orders for this subscription
            $subscription_id = $subscription->get_id();
            error_log("Looking for orders for subscription #$subscription_id");
            
            global $wpdb;
            
            // Get all related orders including parent and renewal orders
            $query = $wpdb->prepare(
                "SELECT DISTINCT o.ID, o.post_date, pm_paid.meta_value as date_paid
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm_paid ON o.ID = pm_paid.post_id AND pm_paid.meta_key = '_date_paid'
                LEFT JOIN {$wpdb->postmeta} pm_rel ON o.ID = pm_rel.post_id 
                WHERE o.post_type = 'shop_order'
                AND o.post_status IN ('wc-completed', 'wc-processing')
                AND (
                    o.ID = %d
                    OR (
                        pm_rel.meta_key IN ('_subscription_renewal', '_subscription_resubscribe', '_subscription_switch')
                        AND pm_rel.meta_value = %d
                    )
                )
                AND pm_paid.meta_value IS NOT NULL
                ORDER BY pm_paid.meta_value DESC, o.post_date DESC
                LIMIT 1",
                $subscription->get_parent_id(),
                $subscription_id
            );
            
            $result = $wpdb->get_row($query);
            error_log("SQL Query for finding latest paid order: " . $query);
            
            $last_valid_order = null;
            if ($result) {
                $order = wc_get_order($result->ID);
                if ($order && $order->get_date_paid()) {
                    $last_valid_order = $order;
                    error_log("Found latest paid order #{$result->ID} with status: " . $order->get_status() . " and paid date: " . $order->get_date_paid()->format('Y-m-d H:i:s'));
                }
            } else {
                // If no order found with the new query, try getting all orders and check manually
                error_log("No order found with main query, trying alternative approach");
                $order_ids = wcs_get_subscription_orders($subscription, 'ids');
                error_log("Found these order IDs: " . implode(', ', $order_ids));
                
                foreach ($order_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    if ($order && 
                        in_array($order->get_status(), ['completed', 'processing']) && 
                        $order->get_date_paid()
                    ) {
                        if (!$last_valid_order || $order->get_date_paid() > $last_valid_order->get_date_paid()) {
                            $last_valid_order = $order;
                            error_log("Found valid order #{$order_id} with status: " . $order->get_status() . " and paid date: " . $order->get_date_paid()->format('Y-m-d H:i:s'));
                        }
                    }
                }
            }
            
            // Update the subscription object
            $subscription->set_billing_period($new_period);
            $subscription->set_billing_interval($new_interval);
            
            // Calculate next payment from last valid order
            // Save all changes
            $subscription->save();
            
            // Force refresh the subscription from the database
            $subscription = wcs_get_subscription($subscription->get_id());
            
            // Update next payment date
            mfx_update_subscription_next_payment($subscription, $new_period, $new_interval);
            
            // Verify the changes
            $saved_interval = $subscription->get_billing_interval();
            $saved_period = $subscription->get_billing_period();
            
            error_log("Verification - Saved values: interval=$saved_interval, period=$saved_period");
            
            if ($saved_interval != $new_interval || $saved_period != $new_period) {
                throw new Exception("Failed to update subscription billing schedule - Expected interval: $new_interval, period: $new_period, got interval: $saved_interval, period: $saved_period");
            }
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Failed to update subscription: " . $e->getMessage());
        }
    } catch (Exception $e) {
        $error_message = 'Error updating subscription period: ' . $e->getMessage();
        error_log($error_message);
        return new WP_Error('update_error', $error_message);
    }
}



/**
 * Update subscription next payment date based on new billing period and interval
 * 
 * @param WC_Subscription $subscription The subscription to update
 * @param string $new_period New billing period (day, week, month, year)
 * @param int $new_interval New billing interval
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function mfx_update_subscription_next_payment($subscription, $new_period, $new_interval) {
    try {
        error_log("Calculating next payment date for subscription #{$subscription->get_id()} with period: $new_period, interval: $new_interval");
        
        // Validate billing period
        $valid_periods = ['day', 'week', 'month', 'year'];
        if (!in_array($new_period, $valid_periods)) {
            throw new Exception("Invalid billing period: $new_period. Must be one of: " . implode(', ', $valid_periods));
        }
        
        // Validate billing interval
        if (!is_numeric($new_interval) || $new_interval < 1) {
            throw new Exception("Invalid billing interval: $new_interval. Must be a positive number.");
        }
        
        // Get all related orders and sort by ID descending
        $orders = $subscription->get_related_orders('all', 'ids');
        error_log("Found related orders: " . implode(', ', $orders));
        
        // Find the most recent paid order
        $last_paid_order = null;
        $latest_paid_date = null;
        
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->is_paid()) {
                $paid_date = $order->get_date_paid();
                if ($paid_date && (!$latest_paid_date || $paid_date > $latest_paid_date)) {
                    $latest_paid_date = $paid_date;
                    $last_paid_order = $order;
                    error_log("Found more recent paid order #{$order_id} with date: " . $latest_paid_date->format('Y-m-d H:i:s'));
                }
            }
        }
        
        // Calculate next payment date
        if ($latest_paid_date) {
            // Use the last paid date as the base for calculation
            $base_date = $latest_paid_date;
            error_log("Using last paid date as base: " . $base_date->format('Y-m-d H:i:s'));
        } else {
            // If no paid orders, use subscription start date
            $base_date = $subscription->get_date('start');
            if (!$base_date) {
                throw new Exception("No valid base date found for next payment calculation");
            }
            error_log("Using subscription start date as base: " . $base_date->format('Y-m-d H:i:s'));
        }
        
        // Calculate the next payment date
        $next_payment = clone $base_date;
        switch ($new_period) {
            case 'day':
                $next_payment->modify("+{$new_interval} days");
                break;
            case 'week':
                $next_payment->modify("+{$new_interval} weeks");
                break;
            case 'month':
                $next_payment->modify("+{$new_interval} months");
                break;
            case 'year':
                $next_payment->modify("+{$new_interval} years");
                break;
        }
        
        error_log("Calculated next payment date: " . $next_payment->format('Y-m-d H:i:s'));
        
        // Update subscription
        $subscription->update_dates(array('next_payment' => $next_payment->format('Y-m-d H:i:s')));
        $subscription->save();
        
        error_log("Successfully updated next payment date for subscription #{$subscription->get_id()}");
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating next payment date: " . $e->getMessage());
        return new WP_Error('next_payment_update_failed', $e->getMessage());
    }
}

// Hook AJAX actions
add_action('wp_ajax_mfx_update_subscription', 'mfx_process_subscription_update');



/**
 * Update subscription billing company from related order
 *
 * @param WC_Subscription $subscription The subscription object
 * @return void
 */
function mfx_update_subscription_billing_company($subscription) {
    error_log('BluePay Debug: mfx_update_subscription_billing_company');
    if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
        error_log('BluePay Debug: Invalid subscription object');
        return;
    }

    $subscription_id = $subscription->get_id();
    $current_company = $subscription->get_billing_company();
    error_log("BluePay Debug: Processing subscription #{$subscription_id} - Current billing company: {$current_company}");

    // Get the related order
    $order_id = $subscription->get_last_order();
    if (!$order_id) {
        error_log("BluePay Debug: No last order found for subscription #{$subscription_id}");
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("BluePay Debug: Could not load order #{$order_id} for subscription #{$subscription_id}");
        return;
    }

    $billing_company = $order->get_billing_company();
    error_log("BluePay Debug: Order #{$order_id} billing company: {$billing_company}");

    if (!empty($billing_company)) {
        try {
            // Try multiple methods to ensure the billing company is set
            $subscription->set_billing_company($billing_company);
            
            // Also update using meta data directly
            update_post_meta($subscription_id, '_billing_company', $billing_company);
            
            // Force a save
            $subscription->save();
            
            // Verify the save worked
            $subscription = wcs_get_subscription($subscription_id);
            $saved_company = $subscription->get_billing_company();
            error_log("BluePay Debug: Verification - Subscription #{$subscription_id} billing company after save: {$saved_company}");
            
            if ($saved_company !== $billing_company) {
                error_log("BluePay Warning: Billing company mismatch after save. Expected: {$billing_company}, Got: {$saved_company}");
                // Try one more time with direct DB update
                global $wpdb;
                $wpdb->update(
                    $wpdb->postmeta,
                    array('meta_value' => $billing_company),
                    array('post_id' => $subscription_id, 'meta_key' => '_billing_company')
                );
            }
            
            error_log("BluePay Debug: Updated subscription #{$subscription_id} billing company to: {$billing_company}");
        } catch (Exception $e) {
            error_log("BluePay Error: Failed to update subscription #{$subscription_id} billing company: " . $e->getMessage());
        }
    }
}

/**
 * Handle subscriptions created for an order
 *
 * @param WC_Order $order The order for which subscriptions have been created
 */
function mfx_handle_subscriptions_created($order) {
    error_log('BluePay Debug: mfx_handle_subscriptions_created');

    if (!$order || !is_a($order, 'WC_Order')) {
        error_log('BluePay Debug: Invalid order object');
        return;
    }

    $order_id = $order->get_id();
    error_log("BluePay Debug: Processing subscriptions for order #{$order_id}");

    // Get all subscriptions for this order
    $subscriptions = wcs_get_subscriptions_for_order($order_id);
    
    if (empty($subscriptions)) {
        error_log("BluePay Debug: No subscriptions found for order #{$order_id}");
        return;
    }

    foreach ($subscriptions as $subscription) {
        mfx_update_subscription_billing_company($subscription);
    }
}

/**
 * Handle subscription status changes
 *
 * @param WC_Subscription $subscription The subscription object
 */
function mfx_handle_subscription_status_change($subscription) {
    error_log('BluePay Debug: mfx_handle_subscription_status_change');
    error_log('BluePay Debug: Subscription #' . $subscription->get_id() . ' status changed to: ' . $subscription->get_status());
    mfx_update_subscription_billing_company($subscription);
}

function mfx_get_team_name_by_user_id($user_id) {
    global $wpdb;

//     // Debug: Display the user ID being searched
//     echo "Searching for user ID: {$user_id}<br>";

    // Prepare the query to find a team where the user is either the author or in _member_id meta
    $query = $wpdb->prepare(
        "SELECT p.post_title 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_member_id'
        WHERE p.post_type = %s 
          AND p.post_status = %s
          AND (p.post_author = %d OR pm.meta_value = %d)
        LIMIT 1",
        'wc_memberships_team',  // Post type
        'publish',              // Post status
        $user_id,               // User ID for author check
        $user_id                // User ID for meta check
    );

    $team_name = $wpdb->get_var($query);

    if ($team_name) {
//         echo "Team found: {$team_name}<br>";
    } else {
//         echo "No team found for user ID: {$user_id}<br>";
    }

    return $team_name ? $team_name : null;
}


// Hook into various subscription events
add_action('subscriptions_created_for_order', 'mfx_handle_subscriptions_created', 10, 2);
add_action('woocommerce_subscription_status_updated', 'mfx_handle_subscription_status_change', 10, 1);
add_action('woocommerce_subscription_payment_complete', 'mfx_handle_subscription_status_change', 10, 1);

