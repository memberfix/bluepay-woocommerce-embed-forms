<?php

function mfx_update_order_status_and_txn_id($order_id, $txn_id) {
    // Load the order
    $order = wc_get_order($order_id); // wc_get_order is preferred for getting order objects
    // Check if the order exists
    if ($order instanceof WC_Order) {
        // Update the order status to 'completed'
        $order->update_status('completed'); // Use standard single quotes
        // Set the payment method and title
        $order->set_payment_method('bluepay');
        $order->set_payment_method_title('Credit Card (BluePay)');
        // Set the transaction ID
        $order->set_transaction_id($txn_id);
        // Add an order note with the invoice ID and status
        $order_note = 'Transaction completed via BluePay. Invoice ID: ' . esc_html($txn_id) . '. Status: Completed.';

        $order->add_order_note($order_note);
        // Save the order
        $order->save();
    }
}

