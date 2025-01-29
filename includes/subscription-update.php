<?php 

function mfx_process_subscription_update() {
    check_ajax_referer('subscription_update_nonce', 'nonce');
    
    $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
    $selected_variations = isset($_POST['selected_variations']) ? $_POST['selected_variations'] : array();
    $selected_plan = isset($_POST['selected_plan']) ? sanitize_text_field($_POST['selected_plan']) : '';
    
    if (!$subscription_id || empty($selected_variations)) {
        error_log('Invalid subscription data - ID: ' . $subscription_id);
        wp_send_json_error('Invalid subscription data');
        return;
    }

    $subscription = wcs_get_subscription($subscription_id);
    if (!$subscription) {
        error_log('Subscription not found - ID: ' . $subscription_id);
        wp_send_json_error('Subscription not found');
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
        wp_send_json_error('No team data found in subscription items or parent order');
        return;
    }
    
    // Update subscription items
    $update_items_result = mfx_update_subscription_items($subscription, $selected_variations, $team_data);
    if (is_wp_error($update_items_result)) {
        error_log('Failed to update subscription items: ' . $update_items_result->get_error_message());
        wp_send_json_error($update_items_result->get_error_message());
        return;
    }
    
    // Update subscription recurring period
    $update_period_result = mfx_update_subscription_recurring_period($subscription, $selected_plan);
    if (is_wp_error($update_period_result)) {
        error_log('Failed to update subscription period: ' . $update_period_result->get_error_message());
        wp_send_json_error($update_period_result->get_error_message());
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
                wp_send_json_error($update_pending_result->get_error_message());
                return;
            }
        } elseif ($order_status === 'failed') {
            $update_failed_result = mfx_update_failed_order_items($last_order, $selected_variations, $team_data);
            if (is_wp_error($update_failed_result)) {
                error_log('Failed to update failed order: ' . $update_failed_result->get_error_message());
                wp_send_json_error($update_failed_result->get_error_message());
                return;
            }
        }
    }
    
    error_log('Subscription ' . $subscription_id . ' updated successfully');
    wp_send_json_success('Subscription updated successfully');
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
        error_log("Starting subscription period update with plan: " . print_r($selected_plan, true));
        
        // Convert plan to lowercase and trim for consistency
        $selected_plan = strtolower(trim($selected_plan));
        
        $period_mapping = array(
            'monthly' => array('interval' => 1, 'period' => 'month'),
            'quarterly' => array('interval' => 3, 'period' => 'month'),
            'annual' => array('interval' => 12, 'period' => 'month'),
            'quarter' => array('interval' => 3, 'period' => 'month'),
            '3month' => array('interval' => 3, 'period' => 'month'),
            '3months' => array('interval' => 3, 'period' => 'month'),
            '3-month' => array('interval' => 3, 'period' => 'month'),
            '3-months' => array('interval' => 3, 'period' => 'month')
        );
        
        error_log("Available period mappings: " . print_r(array_keys($period_mapping), true));
        
        if (!isset($period_mapping[$selected_plan])) {
            $error_message = "Invalid subscription plan selected: '$selected_plan'. Available plans: " . implode(', ', array_keys($period_mapping));
            error_log($error_message);
            return new WP_Error('invalid_plan', $error_message);
        }
        
        $new_interval = $period_mapping[$selected_plan]['interval'];
        $new_period = $period_mapping[$selected_plan]['period'];
        
        error_log("Setting new interval to: $new_interval and period to: $new_period");
        
        try {
            // First set the billing period and interval
            $subscription->set_billing_interval($new_interval);
            error_log("Successfully set billing interval to: $new_interval");
            
            $subscription->set_billing_period($new_period);
            error_log("Successfully set billing period to: $new_period");
            
            // Get the last payment date
            $last_payment = $subscription->get_date('last_payment');
            if (!$last_payment) {
                $last_payment = $subscription->get_date('start');
                error_log("No last payment date found, using start date: $last_payment");
            }
            
            if ($last_payment) {
                // Calculate next payment date
                $next_payment = WC_Subscriptions_Product::get_expiration_date(
                    $last_payment,
                    array(
                        'subscription_interval' => $new_interval,
                        'subscription_period' => $new_period
                    )
                );
                
                error_log("Calculated next payment date: $next_payment from last payment: $last_payment");
                
                // Update the next payment date
                $subscription->update_dates(array(
                    'next_payment' => $next_payment
                ));
                error_log("Successfully updated next payment date to: $next_payment");
            } else {
                error_log("Warning: Could not find last payment or start date");
            }
            
            // Save all changes
            $subscription->save();
            error_log("Successfully saved subscription changes");
            
            // Verify the changes
            $saved_interval = $subscription->get_billing_interval();
            $saved_period = $subscription->get_billing_period();
            $saved_next_payment = $subscription->get_date('next_payment');
            
            error_log("Verification - Saved values: interval=$saved_interval, period=$saved_period, next_payment=$saved_next_payment");
            
            if ($saved_interval != $new_interval || $saved_period != $new_period) {
                throw new Exception("Verification failed - saved values don't match expected values");
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

// Hook AJAX actions
add_action('wp_ajax_mfx_update_subscription', 'mfx_process_subscription_update');
