<?php

/**
 * Update order status and transaction ID after successful BluePay payment
 *
 * @param string|int $order_id The WooCommerce order ID
 * @param string $txn_id The BluePay transaction ID
 * @return bool True on success, false on failure
 */
function mfx_update_order_status_and_txn_id($order_id, $txn_id) {
    error_log('BluePay: Starting order status update for order #' . $order_id . ' with transaction ID: ' . $txn_id);

    if (empty($order_id) || empty($txn_id)) {
        error_log('BluePay: Missing order ID or transaction ID');
        return false;
    }

    // Sanitize inputs
    error_log('BluePay: Sanitizing inputs');
    $order_id = absint($order_id);
    $txn_id = sanitize_text_field($txn_id);
    error_log('BluePay: Sanitized order_id=' . $order_id . ', txn_id=' . $txn_id);

    try {
        error_log('BluePay: Attempting to load order #' . $order_id);
        // Load the order
        $order = wc_get_order($order_id);
        
        // Check if the order exists and is valid
        if (!$order instanceof WC_Order) {
            error_log('BluePay: Invalid order ID ' . $order_id);
            return false;
        }

        // Prevent duplicate transaction IDs
        if ($order->get_transaction_id() === $txn_id) {
            error_log('BluePay: Transaction ID ' . $txn_id . ' already set for order #' . $order_id);
            return true; // Return true as this isn't really an error
        }

        error_log('BluePay: Setting payment method and details for order #' . $order_id);
        // Set payment method and title
        $order->set_payment_method('bluepay');
        $order->set_payment_method_title('Credit Card (BluePay)');
        $order->set_transaction_id($txn_id);

        // Add order note
        $order_note = sprintf(
            'Transaction completed via BluePay. Invoice ID: %s. Status: Completed.',
            esc_html($txn_id)
        );
        $order->add_order_note($order_note);
        error_log('BluePay: Added completion note to order #' . $order_id);

        // Mark as paid if not already
        error_log('BluePay: Checking payment status for order #' . $order_id);
        if (!$order->is_paid()) {
            error_log('BluePay: Order #' . $order_id . ' not marked as paid, completing payment');
            // Trigger payment complete which handles stock reduction internally
            $order->payment_complete($txn_id);
        } else {
            error_log('BluePay: Order #' . $order_id . ' already paid, checking stock reduction');
            // If already paid, just update status and handle stock manually if needed
            if (!wc_get_order($order->get_id())->get_meta('_order_stock_reduced', true)) {
                error_log('BluePay: Reducing stock levels for order #' . $order_id);
                wc_reduce_stock_levels($order);
            }
            error_log('BluePay: Updating order #' . $order_id . ' status to completed');
            $order->update_status('completed');
        }

        // Handle subscriptions if WooCommerce Subscriptions is active
        error_log('BluePay: Checking for subscriptions in order #' . $order_id);
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            error_log('BluePay: Order #' . $order_id . ' contains subscriptions, processing them');
            try {
                $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), array('order_type' => 'any'));
                error_log('BluePay: Found ' . count($subscriptions) . ' subscription(s) for order #' . $order_id);
                
                foreach ($subscriptions as $subscription) {
                    if (!$subscription instanceof WC_Subscription) {
                        error_log('BluePay: Invalid subscription object for order #' . $order_id);
                        continue;
                    }

                    // Prevent processing cancelled subscriptions
                    if ($subscription->has_status('cancelled')) {
                        error_log('BluePay: Skipping cancelled subscription #' . $subscription->get_id());
                        continue;
                    }

                    error_log('BluePay: Processing subscription #' . $subscription->get_id() . ' for order #' . $order_id);
                    // Update subscription payment details
                    $subscription->set_payment_method('bluepay');
                    $subscription->set_payment_method_title('Credit Card (BluePay)');
                    error_log('BluePay: Updated payment method for subscription #' . $subscription->get_id());
                    
                    // Remove existing meta to prevent duplicates
                    error_log('BluePay: Removing old meta data from subscription #' . $subscription->get_id());
                    $subscription->delete_meta_data('_bluepay_card_id');
                    $subscription->delete_meta_data('_bluepay_customer_id');
                    
                    // Add new meta data
                    error_log('BluePay: Adding new meta data to subscription #' . $subscription->get_id());
                    $subscription->add_meta_data('_bluepay_card_id', $txn_id);
                    $subscription->add_meta_data('_bluepay_customer_id', $txn_id);

                    // Activate subscription if pending or on-hold
                    error_log('BluePay: Checking subscription #' . $subscription->get_id() . ' status: ' . $subscription->get_status());
                    if ($subscription->has_status(array('pending', 'on-hold'))) {
                        $old_status = $subscription->get_status();
                        error_log('BluePay: Activating subscription #' . $subscription->get_id() . ' (changing from ' . $old_status . ' to active)');
                        $subscription->update_status('active');
                        do_action('woocommerce_subscription_payment_complete', $subscription);
                        
                        // Add detailed activation note
                        $note = sprintf(
                            'Subscription status changed from %s to active via BluePay payment (Transaction ID: %s).',
                            $old_status,
                            $txn_id
                        );
                        $subscription->add_order_note($note);
                        error_log('BluePay: Added activation note to subscription #' . $subscription->get_id() . ': ' . $note);
                    }

                    $subscription->save();
                }
            } catch (Exception $e) {
                error_log('BluePay: Error processing subscription for order #' . $order_id . ': ' . $e->getMessage());
                // Continue processing the order even if subscription update fails
            }
        }

        error_log('BluePay: Saving all changes for order #' . $order_id);
        // Save all changes
        $order->save();

        error_log('BluePay: Triggering WooCommerce actions for order #' . $order_id);
        // Trigger WooCommerce actions after successful save
        do_action('woocommerce_payment_complete', $order->get_id());
        do_action('woocommerce_order_status_completed', $order->get_id(), $order);

        error_log('BluePay: Successfully completed order status update for order #' . $order_id);
        return true;

    } catch (Exception $e) {
        error_log('BluePay: Error processing order #' . $order_id . ': ' . $e->getMessage());
        return false;
    }
}
