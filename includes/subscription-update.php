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

function mfx_update_subscription_next_payment($subscription, $new_period, $new_interval) {
    try {
        error_log("Calculating next payment date for subscription #{$subscription->get_id()} with period: $new_period, interval: $new_interval");
        
        // Validate input parameters
        $valid_periods = ['day', 'week', 'month', 'year'];
        if (!in_array($new_period, $valid_periods)) {
            throw new Exception("Invalid billing period: $new_period. Must be one of: " . implode(', ', $valid_periods));
        }
        
        if (!is_numeric($new_interval) || $new_interval < 1) {
            throw new Exception("Invalid billing interval: $new_interval. Must be a positive number.");
        }
        
        // Get all related orders
        $orders = $subscription->get_related_orders('all', 'ids');
        error_log("Found related orders: " . implode(', ', $orders));
        
        $last_paid_order = null;
        $latest_paid_date = null;
        
        // Find the most recent paid order
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && 
                in_array($order->get_status(), ['completed', 'processing']) && 
                $order->get_date_paid()
            ) {
                $paid_date = $order->get_date_paid();
                if (!$latest_paid_date || $paid_date > $latest_paid_date) {
                    $last_paid_order = $order;
                    $latest_paid_date = $paid_date;
                    error_log("Found valid paid order #{$order_id} with status: " . $order->get_status() . " and paid date: " . $paid_date->format('Y-m-d H:i:s'));
                }
            }
        }
        
        if ($last_paid_order && $latest_paid_date) {
            $last_order_timestamp = $latest_paid_date->getTimestamp();
            $current_timestamp = current_time('timestamp');
            
            // Calculate next payment date from the last order date
            switch ($new_period) {
                case 'day':
                    $next_payment = strtotime("+{$new_interval} day", $last_order_timestamp);
                    break;
                case 'week':
                    $next_payment = strtotime("+{$new_interval} week", $last_order_timestamp);
                    break;
                case 'month':
                    $next_payment = strtotime("+{$new_interval} month", $last_order_timestamp);
                    break;
                case 'year':
                    $next_payment = strtotime("+{$new_interval} year", $last_order_timestamp);
                    break;
            }
            
            if ($next_payment) {
                // If calculated next payment is in the past, calculate from current time instead
                if ($next_payment < $current_timestamp) {
                    error_log("Calculated next payment date is in the past. Recalculating from current time.");
                    switch ($new_period) {
                        case 'day':
                            $next_payment = strtotime("+{$new_interval} day", $current_timestamp);
                            break;
                        case 'week':
                            $next_payment = strtotime("+{$new_interval} week", $current_timestamp);
                            break;
                        case 'month':
                            $next_payment = strtotime("+{$new_interval} month", $current_timestamp);
                            break;
                        case 'year':
                            $next_payment = strtotime("+{$new_interval} year", $current_timestamp);
                            break;
                    }
                }
                
                $next_payment_date = date('Y-m-d H:i:s', $next_payment);
                error_log("Setting next payment date to: $next_payment_date");
                
                // Update the next payment date
                $dates_to_update = array('next_payment' => $next_payment_date);
                $subscription->update_dates($dates_to_update);
                $subscription->save();
                
                return true;
            } else {
                throw new Exception("Failed to calculate next payment date");
            }
        } else {
            error_log("No paid orders found with completed/processing status for subscription #{$subscription->get_id()}");
            return false;
        }
    } catch (Exception $e) {
        error_log("Error updating next payment date: " . $e->getMessage());
        throw $e; // Re-throw the exception to be handled by the caller
    }
}

// Hook AJAX actions
add_action('wp_ajax_mfx_update_subscription', 'mfx_process_subscription_update');
