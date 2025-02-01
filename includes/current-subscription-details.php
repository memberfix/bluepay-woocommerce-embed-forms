<?php

function mfx_get_current_subscription_details() {
    $current_user_id = get_current_user_id();
    $subscriptions = wcs_get_users_subscriptions($current_user_id);
    
    if (empty($subscriptions)) {
        echo '<p>No active subscriptions found.</p>';
        return;
    }

    foreach ($subscriptions as $subscription) {
        $next_payment = $subscription->get_date('next_payment');
        $last_payment = $subscription->get_date('last_payment');
        $total = $subscription->get_total();
        
        // Get the latest paid order
        $orders = $subscription->get_related_orders('all', 'ids');
        $last_paid_order = null;
        $latest_paid_date = null;
        
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
                }
            }
        }
        
        ?>
        <div class="subscription-info">
            <h4>Subscription #<?php echo esc_html($subscription->get_id()); ?></h4>
            
            <div class="subscription-dates">
                <p><strong>Next Payment:</strong> 
                    <?php echo $next_payment ? esc_html($next_payment->format('F j, Y')) : 'N/A'; ?>
                </p>
                <p><strong>Last Payment:</strong> 
                    <?php echo $latest_paid_date ? esc_html($latest_paid_date->format('F j, Y')) : 'N/A'; ?>
                </p>
                <p><strong>Total Amount:</strong> 
                    <?php echo wc_price($total); ?>
                </p>
                <p><strong>Status:</strong> 
                    <?php echo esc_html(wcs_get_subscription_status_name($subscription->get_status())); ?>
                </p>
            </div>

            <div class="subscription-items">
                <h5>Subscription Items:</h5>
                <?php foreach ($subscription->get_items() as $item): ?>
                    <div class="item-info">
                        <p class="item-name">
                            <strong><?php echo esc_html($item->get_name()); ?></strong>
                            <?php if ($item->get_quantity() > 1): ?>
                                <span class="item-quantity">× <?php echo esc_html($item->get_quantity()); ?></span>
                            <?php endif; ?>
                        </p>
                        
                        <?php 
                        // Display variation attributes
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

// Add the output to the product filter shortcode
add_action('mfx_display_current_subscription_details', 'mfx_get_current_subscription_details');
