<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Set billing company name from order item team_name meta
 * This runs after the order items are added and their meta data is set
 * 
 * @param int $order_id WooCommerce order ID
 * @return void
 */


// Hook into checkout validation to update billing company from team_name
//
/**
 * Set billing company name from team name in order item meta
 * This runs after the order is created and items are added
 * 
 * @param int $order_id The WooCommerce order ID
 * @return void
 */
function mfx_set_billing_company($order_id) {
    error_log('BluePay: Setting billing company for order #' . $order_id);
    
    // Get the order
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('BluePay Debug: Could not find order #' . $order_id);
        return;
    }
    
    // Get order items
    $items = $order->get_items();
    error_log('BluePay Debug - Order items: ' . print_r($items, true));
    
    // Try to find team_name in order item meta
    foreach ($items as $item) {
        error_log('BluePay Debug - Checking item #' . $item->get_id() . ' meta');
        $team_name = $item->get_meta('team_name');
        
        if (!empty($team_name)) {
            error_log('BluePay: Found team_name in item #' . $item->get_id() . ': ' . $team_name);
            
            // Set the billing company
            $order->set_billing_company($team_name);
            $order->save();
            
            error_log('BluePay: Set billing_company for order #' . $order_id . ' to: ' . $team_name);
            return;
        }
    }
    
    error_log('BluePay: No team_name found in any order items for order #' . $order_id);
}

// Hook into order creation to update billing company from team_name
add_action('woocommerce_checkout_order_created', 'mfx_set_billing_company', 10, 1);
