<?php

/**
 * Display current subscription details for the logged-in user
 * Includes subscription items, payment dates, and status information
 * Output is not cached and dynamically loaded each time
 * 
 * Usage as shortcode: [mfx_subscription_details]
 * 
 * @param array $atts Shortcode attributes (not used currently)
 * @return string HTML output of subscription details
 */
function mfx_get_current_subscription_details($atts = array()) {
    // Prevent caching
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    
    error_log("DEBUG: Starting subscription details function");
    
    // Start output buffering to capture the HTML output
    ob_start();
    
    // Get current user's subscriptions
    $current_user_id = get_current_user_id();
    $subscriptions = wcs_get_users_subscriptions($current_user_id);
    
    if (empty($subscriptions)) {
        error_log("DEBUG: No active subscriptions found for user ID: " . $current_user_id);
        echo '<p>No active subscriptions found.</p>';
        return ob_get_clean();
    }

    error_log("DEBUG: Found " . count($subscriptions) . " subscriptions");
    
    // Add timestamp to force dynamic content
    echo '<!-- Subscription details generated at: ' . current_time('mysql') . ' -->';
    
    foreach ($subscriptions as $subscription) {
        $subscription_id = $subscription->get_id();
        error_log("DEBUG: Processing subscription #{$subscription_id}");
        
        // Get subscription dates with proper type handling
        $next_payment = $subscription->get_date('next_payment');
        error_log("DEBUG: Raw next_payment value: " . print_r($next_payment, true));
        error_log("DEBUG: Next payment type: " . gettype($next_payment));
        error_log("DEBUG: Next payment from meta: " . print_r($subscription->get_meta('_schedule_next_payment'), true));
        
        // Try to get next payment from schedule meta
        if (empty($next_payment)) {
            $next_payment = $subscription->get_meta('_schedule_next_payment');
            error_log("DEBUG: Using next payment from meta: " . print_r($next_payment, true));
        }
        
        if ($next_payment) {
            if (is_string($next_payment)) {
                error_log("DEBUG: Converting string date to timestamp: {$next_payment}");
                $timestamp = wcs_date_to_time($next_payment);
                error_log("DEBUG: Timestamp after conversion: {$timestamp}");
                if ($timestamp) {
                    $next_payment = new WC_DateTime("@{$timestamp}");
                    error_log("DEBUG: Created WC_DateTime object");
                }
            } elseif (is_numeric($next_payment)) {
                error_log("DEBUG: Converting numeric timestamp: {$next_payment}");
                $next_payment = new WC_DateTime("@{$next_payment}");
            }
        }
        
        error_log("DEBUG: Final next_payment object: " . ($next_payment instanceof WC_DateTime ? 'WC_DateTime' : gettype($next_payment)));
        
        $start_date = $subscription->get_date('start');
        if (is_string($start_date)) {
            $start_date = wcs_date_to_time($start_date);
            $start_date = new WC_DateTime("@$start_date");
        }
        
        $last_order_date = $subscription->get_date('last_order_date_created');
        if (is_string($last_order_date)) {
            $last_order_date = wcs_date_to_time($last_order_date);
            $last_order_date = new WC_DateTime("@$last_order_date");
        }
        
        // Get renewal orders
        $renewal_orders = $subscription->get_related_orders('all', 'renewal');
        error_log("DEBUG: Found " . count($renewal_orders) . " renewal orders");

        // Sort orders by ID in descending order to get the most recent first
        usort($renewal_orders, function($a, $b) {
            return $b->get_id() - $a->get_id();
        });

        // Find the most recent paid order and payment date
        $last_payment_date = null;
        $last_paid_order = null;
        
        foreach ($renewal_orders as $order) {
            $order_id = $order->get_id();
            if ($order->is_paid() && $order->get_date_paid()) {
                error_log("Found paid order #{$order_id}");
                $payment_date = $order->get_date_paid();
                
                if (!$last_payment_date || $payment_date > $last_payment_date) {
                    $last_payment_date = $payment_date;
                    $last_paid_order = $order;
                    error_log("Updated last payment date: " . $last_payment_date->date_i18n('Y-m-d H:i:s'));
                }
            } else {
                error_log("Order #{$order_id} is not paid");
            }
        }

        // Get subscription total and status
        $total = $subscription->get_total();
        $status = $subscription->get_status();
        
        // Get team name from subscription
        $team_name = '';
        foreach ($subscription->get_items() as $item) {
            if ($variation_data = $item->get_formatted_meta_data()) {
                foreach ($variation_data as $meta) {
                    if (strtolower($meta->key) === 'team_name') {
                        $team_name = $meta->value;
                        break 2;
                    }
                }
            }
        }

        // Get billing period and interval
        $billing_period = $subscription->get_billing_period();
        $billing_interval = $subscription->get_billing_interval();
        $recurring_amount = $subscription->get_total();
        
        // Format recurring period text
        $recurring_period = sprintf(
            '%s%s %s',
            $billing_interval > 1 ? $billing_interval . ' ' : '',
            $billing_interval > 1 ? $billing_period . 's' : $billing_period,
            wc_price($recurring_amount)
        );
        
        ?>
        <div class="subscription-info" data-subscription-id="<?php echo esc_attr($subscription_id); ?>">
            <!-- Team Name -->
            <?php if (!empty($team_name)): ?>
            <h3 class="team-name"><?php echo esc_html($team_name); ?></h3>
            <?php endif; ?>
            
            <!-- Subscription Header -->
            <div class="subscription-header">
                <p class="subscription-id">#<?php echo esc_html($subscription_id); ?></p>
                <p class="subscription-status <?php echo esc_attr($status); ?>">
                    <?php echo esc_html(wcs_get_subscription_status_name($status)); ?>
                </p>
            </div>

            <!-- Subscription Overview -->
            <div class="subscription-overview">
                <p class="recurring-total">
                    <span class="label">Recurring:</span>
                    <span class="value"><?php echo ($recurring_period === 'year') ? 'Annual' : $recurring_period; ?></span>
                </p>
                
                <?php if ($last_payment_date instanceof WC_DateTime): ?>
                <p class="last-payment">
                    <span class="label">Last Payment:</span>
                    <span class="value"><?php echo esc_html($last_payment_date->date_i18n('F j, Y')); ?></span>
                </p>
                <?php endif; ?>
                
                <?php 
                error_log("DEBUG: Next payment object type: " . (is_object($next_payment) ? get_class($next_payment) : gettype($next_payment)));
                if ($next_payment instanceof WC_DateTime): 
                    error_log("DEBUG: Next payment date is valid WC_DateTime");
                ?>
                <p class="next-payment">
                    <span class="label">Next Payment:</span>
                    <span class="value"><?php echo esc_html($next_payment->date_i18n('F j, Y')); ?></span>
                </p>
                <?php else:
                    error_log("DEBUG: Next payment date is not a valid WC_DateTime instance");
                endif; ?>
            </div>

            <!-- Subscription Items -->
            <div class="subscription-items">
                <table class="subscription-items-table">
                    <thead>
                        <tr>
                            <th class="product-name">Product</th>
                            <th class="product-total">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subscription->get_items() as $item): ?>
                        <tr class="subscription-item">
                            <td class="product-name">
                                <?php 
                                echo esc_html($item->get_name());
                                if ($item->get_quantity() > 1) {
                                    echo ' <strong class="product-quantity">×&nbsp;' . esc_html($item->get_quantity()) . '</strong>';
                                }
                                
                                // Display variation data
                                $meta_data = $item->get_meta_data();
                                if (!empty($meta_data)) {
                                    echo '<dl class="variation">';
                                    foreach ($meta_data as $meta) {
                                        // Display annual-revenue and location attributes
                                        if (in_array($meta->key, ['annual-revenue', 'location'])) {
                                            $display_key = str_replace('attribute_', '', $meta->key);
                                            $display_key = ucwords(str_replace('-', ' ', $display_key));
                                            printf('<dt>%s:</dt><dd>%s</dd>', 
                                                esc_html($display_key),
                                                esc_html($meta->value)
                                            );
                                        }
                                    }
                                    echo '</dl>';
                                }
                                ?>
                            </td>
                            <td class="product-total">
                                <?php echo wc_price($item->get_total()); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="order-total">
                            <th>Total:</th>
                            <td><?php echo wc_price($total); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php
    }
    
    // Return the buffered output
    return ob_get_clean();
}

// Register shortcode [mfx_subscription_details]
add_shortcode('mfx_subscription_details', 'mfx_get_current_subscription_details');

// Hook the function to display subscription details with cache prevention
add_action('mfx_display_current_subscription_details', function() {
    echo mfx_get_current_subscription_details();
});

// Add AJAX action to refresh subscription details
add_action('wp_ajax_refresh_subscription_details', function() {
    echo mfx_get_current_subscription_details();
    wp_die();
});
add_action('wp_ajax_nopriv_refresh_subscription_details', function() {
    echo mfx_get_current_subscription_details();
    wp_die();
});