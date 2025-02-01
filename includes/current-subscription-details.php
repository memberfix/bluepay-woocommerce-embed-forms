<?php

/**
 * Display current subscription details for the logged-in user
 * Includes subscription items, payment dates, and status information
 */
function mfx_get_current_subscription_details() {
    error_log("DEBUG: Starting subscription details function");
    
    // Get current user's subscriptions
    $current_user_id = get_current_user_id();
    $subscriptions = wcs_get_users_subscriptions($current_user_id);
    
    if (empty($subscriptions)) {
        error_log("DEBUG: No active subscriptions found for user ID: " . $current_user_id);
        echo '<p>No active subscriptions found.</p>';
        return;
    }

    error_log("DEBUG: Found " . count($subscriptions) . " subscriptions");
    
    foreach ($subscriptions as $subscription) {
        $subscription_id = $subscription->get_id();
        error_log("DEBUG: Processing subscription #{$subscription_id}");
        
        // Get subscription dates
        $next_payment = $subscription->get_date('next_payment');
        $start_date = $subscription->get_date('start');
        $last_order_date = $subscription->get_date('last_order_date_created');
        
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
        
        // Display subscription information
        ?>
        <div class="subscription-info">
            <h4>Subscription #<?php echo esc_html($subscription_id); ?></h4>
            
            <!-- Subscription Dates -->
            <div class="subscription-dates">
                <p>
                    <strong>Start Date:</strong>
                    <?php echo ($start_date instanceof WC_DateTime) ? esc_html($start_date->date_i18n('F j, Y')) : 'N/A'; ?>
                </p>
                <p>
                    <strong>Next Payment:</strong>
                    <?php echo ($next_payment instanceof WC_DateTime) ? esc_html($next_payment->date_i18n('F j, Y')) : 'N/A'; ?>
                </p>
                <p>
                    <strong>Last Order Date:</strong>
                    <?php echo ($last_order_date instanceof WC_DateTime) ? esc_html($last_order_date->date_i18n('F j, Y')) : 'N/A'; ?>
                </p>
                <?php if ($last_payment_date instanceof WC_DateTime): ?>
                <p>
                    <strong>Last Payment Date:</strong>
                    <?php echo esc_html($last_payment_date->date_i18n('F j, Y')); ?>
                </p>
                <?php endif; ?>
            </div>

            <!-- Subscription Details -->
            <div class="subscription-details">
                <p><strong>Total Amount:</strong> <?php echo wc_price($total); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html(wcs_get_subscription_status_name($status)); ?></p>
            </div>

            <!-- Subscription Items -->
            <div class="subscription-items">
                <h5>Items in this Subscription:</h5>
                <?php foreach ($subscription->get_items() as $item): ?>
                    <div class="item-info">
                        <!-- Item Name and Quantity -->
                        <p class="item-name">
                            <strong><?php echo esc_html($item->get_name()); ?></strong>
                            <?php if ($item->get_quantity() > 1): ?>
                                <span class="item-quantity">× <?php echo esc_html($item->get_quantity()); ?></span>
                            <?php endif; ?>
                        </p>
                        
                        <!-- Variation Details -->
                        <?php 
                        $variation_data = $item->get_formatted_meta_data();
                        if (!empty($variation_data)): ?>
                            <div class="variation-details">
                                <?php foreach ($variation_data as $meta): ?>
                                    <p class="variation-attribute">
                                        <span class="attribute-label"><?php echo esc_html($meta->display_key); ?>:</span>
                                        <span class="attribute-value"><?php echo wp_kses_post($meta->display_value); ?></span>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Item Total -->
                        <p class="item-total">
                            <strong>Item Total:</strong> <?php echo wc_price($item->get_total()); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}

// Hook the function to display subscription details
add_action('mfx_display_current_subscription_details', 'mfx_get_current_subscription_details');